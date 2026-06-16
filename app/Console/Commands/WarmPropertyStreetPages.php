<?php

namespace App\Console\Commands;

use App\Http\Controllers\PropertyStreetController;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class WarmPropertyStreetPages extends Command
{
    private const TARGET_BATCH_SIZE = 500;

    private const NO_PROGRESS_LOG_INTERVAL = 500;

    protected $signature = 'property:street-warm
                            {--min-sales=5 : Minimum Category A sales required for a street+outcode target}
                            {--limit=0 : Limit the number of street+outcode pages to warm (0 = all)}
                            {--outcode= : Only warm a specific outcode}
                            {--refresh : Forget an existing street cache before rebuilding it}
                            {--skip-existing : Skip pages that already have a street cache entry}
                            {--shards=1 : Split the warm into this many deterministic shards}
                            {--shard=0 : Warm only this zero-based shard number}
                            {--no-progress : Disable the progress bar for lower console overhead}';

    protected $description = 'Warm cached payloads for property street pages.';

    public function handle(PropertyStreetController $controller): int
    {
        if (! Schema::hasTable('land_registry')) {
            $this->error('Missing land_registry table.');

            return self::FAILURE;
        }

        DB::connection()->disableQueryLog();
        $controller->enableWarmProfiling(fn (string $message) => $this->line($message));

        $minSales = max(1, (int) $this->option('min-sales'));
        $limit = max(0, (int) $this->option('limit'));
        $refresh = (bool) $this->option('refresh');
        $skipExisting = (bool) $this->option('skip-existing');
        $noProgress = (bool) $this->option('no-progress');
        $outcodeFilter = $this->normalizeOutcode((string) $this->option('outcode'));
        $shards = max(1, (int) $this->option('shards'));
        $shard = max(0, (int) $this->option('shard'));

        if ($shard >= $shards) {
            $this->error('The --shard option must be zero-based and less than --shards. Example: --shards=4 --shard=0,1,2,3');

            return self::FAILURE;
        }

        $baseQuery = $this->qualifyingStreetQuery($minSales, $outcodeFilter, $shards, $shard);
        $total = (int) DB::query()->fromSub(
            $limit > 0 ? (clone $baseQuery)->limit($limit) : $baseQuery,
            'street_targets'
        )->count();

        if ($total === 0) {
            $this->warn('No qualifying street pages found to warm.');
            foreach ($this->diagnosticStats() as $label => $value) {
                $this->line($label.': '.number_format($value));
            }
            $this->line('Diagnostic SQL:');
            $this->line($this->diagnosticSql($minSales, $outcodeFilter));

            return self::SUCCESS;
        }

        $this->info('Warming '.$total.' street page caches...');
        $this->line('Minimum sales threshold: '.number_format($minSales));
        if ($outcodeFilter !== null) {
            $this->line('Outcode filter: '.$outcodeFilter);
        }
        if ($shards > 1) {
            $this->line('Shard: '.($shard + 1).' of '.$shards.' (zero-based --shard='.$shard.')');
        }
        if ($skipExisting) {
            $this->line('Skipping existing cache entries.');
        }

        if (! $noProgress) {
            $this->output->progressStart($total);
        }
        $startedAt = microtime(true);

        $warmed = 0;
        $skipped = 0;
        $failed = 0;

        $processed = 0;
        $page = 1;

        while (true) {
            $remaining = $limit > 0 ? max(0, $limit - (($page - 1) * self::TARGET_BATCH_SIZE)) : null;

            if ($remaining === 0) {
                break;
            }

            $batchSize = $remaining !== null
                ? min(self::TARGET_BATCH_SIZE, $remaining)
                : self::TARGET_BATCH_SIZE;

            $targets = (clone $baseQuery)
                ->forPage($page, $batchSize)
                ->get();

            if ($targets->isEmpty()) {
                break;
            }

            foreach ($targets as $target) {
                $street = trim((string) ($target->street ?? ''));
                $outcode = strtoupper(trim((string) ($target->outcode ?? '')));
                $streetSlug = Str::slug($street);

                try {
                    $cacheKey = PropertyStreetController::cacheKey($streetSlug, $outcode);

                    if ($refresh) {
                        Cache::forget($cacheKey);
                    }

                    if ($skipExisting && Cache::has($cacheKey)) {
                        $skipped++;
                    } else {
                        $controller->warmStreetCache($streetSlug, $outcode);
                        $warmed++;
                    }
                } catch (Throwable $throwable) {
                    $failed++;
                    $this->newLine();
                    $this->error("Failed warming {$street}, {$outcode}: ".$throwable->getMessage());
                }

                $processed++;

                if (! $noProgress) {
                    $this->output->progressAdvance();
                } elseif ($processed % self::NO_PROGRESS_LOG_INTERVAL === 0) {
                    $this->logNoProgressStats($processed, $warmed, $skipped, $failed, $startedAt);
                }
            }

            unset($targets);
            $page++;
        }

        if (! $noProgress) {
            $this->output->progressFinish();
            $this->newLine();
        }

        Cache::put(
            'property:street:last_warm:min'.$minSales.($outcodeFilter !== null ? ':'.$outcodeFilter : ''),
            now()->toIso8601String(),
            now()->addDays(45)
        );

        $elapsed = round(microtime(true) - $startedAt, 2);
        $this->info("Street page warm complete in {$elapsed}s");
        $this->line('Warmed: '.number_format($warmed));
        $this->line('Skipped: '.number_format($skipped));
        $this->line('Failed: '.number_format($failed));
        $this->line('Slowest sections:');
        foreach (array_slice($controller->warmProfilingSummary(), 0, 10) as $summary) {
            $this->line(sprintf(
                '%s total_ms=%.2f avg_ms=%.2f max_ms=%.2f count=%d',
                $summary['section'],
                $summary['total_ms'],
                $summary['avg_ms'],
                $summary['max_ms'],
                $summary['count']
            ));
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function qualifyingStreetQuery(int $minSales, ?string $outcodeFilter, int $shards = 1, int $shard = 0)
    {
        $outcodeExpression = $this->outcodeExpression();

        $query = DB::table('land_registry')
            ->selectRaw('TRIM("Street") as street')
            ->selectRaw($outcodeExpression.' as outcode')
            ->selectRaw('COUNT(*) as sales_count')
            ->whereRaw('"PPDCategoryType" = ?', ['A'])
            ->whereRaw('"Street" IS NOT NULL')
            ->whereRaw('TRIM("Street") <> ?', [''])
            ->whereRaw('"Postcode" IS NOT NULL')
            ->whereRaw('TRIM("Postcode") <> ?', ['']);

        if ($outcodeFilter !== null) {
            $query->whereRaw($outcodeExpression.' = ?', [$outcodeFilter]);
        }
        if ($shards > 1) {
            $query->whereRaw($this->shardExpression($outcodeExpression).' = ?', [$shards, $shard]);
        }

        return $query
            ->groupByRaw('TRIM("Street"), '.$outcodeExpression)
            ->havingRaw('COUNT(*) >= ?', [$minSales])
            ->orderByRaw($outcodeExpression)
            ->orderByRaw('TRIM("Street")');
    }

    private function outcodeExpression(): string
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            return 'UPPER(SPLIT_PART("Postcode", \' \', 1))';
        }

        return 'UPPER(TRIM(SUBSTR("Postcode", 1, CASE WHEN INSTR("Postcode", \' \') = 0 THEN LENGTH("Postcode") ELSE INSTR("Postcode", \' \') - 1 END)))';
    }

    private function shardExpression(string $outcodeExpression): string
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            return 'MOD(ABS(HASHTEXT('.$outcodeExpression.' || \'|\' || TRIM("Street"))), ?)';
        }

        return 'MOD(CRC32(CONCAT('.$outcodeExpression.', \'|\', TRIM("Street"))), ?)';
    }

    private function normalizeOutcode(string $outcode): ?string
    {
        $normalized = strtoupper(preg_replace('/\s+/', '', trim($outcode)) ?? '');

        return $normalized !== '' ? $normalized : null;
    }

    private function logNoProgressStats(int $processed, int $warmed, int $skipped, int $failed, float $startedAt): void
    {
        $elapsed = max(microtime(true) - $startedAt, 0.001);
        $rate = $processed / $elapsed;
        $total = $warmed + $skipped + $failed;

        $this->info(sprintf(
            '[%s] processed=%s warmed=%s skipped=%s failed=%s total-seen=%s rate=%.2f/sec',
            now()->format('H:i:s'),
            number_format($processed),
            number_format($warmed),
            number_format($skipped),
            number_format($failed),
            number_format($total),
            $rate
        ));
    }

    private function diagnosticSql(int $minSales, ?string $outcodeFilter): string
    {
        $sql = <<<'SQL'
SELECT
  UPPER(SPLIT_PART("Postcode", ' ', 1)) AS outcode,
  BTRIM("Street") AS street,
  COUNT(*) AS sales_count
FROM land_registry
WHERE "PPDCategoryType" = 'A'
  AND "Street" IS NOT NULL
  AND BTRIM("Street") <> ''
  AND "Postcode" IS NOT NULL
  AND BTRIM("Postcode") <> ''
SQL;

        if ($outcodeFilter !== null) {
            $sql .= "\n  AND UPPER(SPLIT_PART(\"Postcode\", ' ', 1)) = '".$outcodeFilter."'";
        }

        return $sql."\nGROUP BY 1, 2\nHAVING COUNT(*) >= ".$minSales."\nLIMIT 10;";
    }

    /**
     * @return array<string, int>
     */
    private function diagnosticStats(): array
    {
        return [
            'land_registry rows' => (int) DB::table('land_registry')->count(),
            'category A rows' => (int) DB::table('land_registry')
                ->whereRaw('"PPDCategoryType" = ?', ['A'])
                ->count(),
            'non-empty street/postcode rows' => (int) DB::table('land_registry')
                ->whereRaw('"Street" IS NOT NULL')
                ->whereRaw('TRIM("Street") <> ?', [''])
                ->whereRaw('"Postcode" IS NOT NULL')
                ->whereRaw('TRIM("Postcode") <> ?', [''])
                ->count(),
        ];
    }
}
