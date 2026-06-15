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

        $targetQuery = $limit > 0
            ? (clone $baseQuery)->limit($limit)
            : $baseQuery;

        foreach ($targetQuery->cursor() as $target) {
            $street = trim((string) ($target->street ?? ''));
            $outcode = strtoupper(trim((string) ($target->outcode ?? '')));
            $streetSlug = Str::slug($street);

            try {
                $cacheKey = PropertyStreetController::cacheKey($streetSlug, $outcode);

                if ($refresh) {
                    Cache::forget($cacheKey);
                } elseif ($skipExisting && Cache::has($cacheKey)) {
                    $skipped++;

                    if (! $noProgress) {
                        $this->output->progressAdvance();
                    }

                    continue;
                }

                $controller->warmStreetCache($streetSlug, $outcode);
                $warmed++;
            } catch (Throwable $throwable) {
                $failed++;
                $this->newLine();
                $this->error("Failed warming {$street}, {$outcode}: ".$throwable->getMessage());
            }

            if (! $noProgress) {
                $this->output->progressAdvance();
            }
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

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function qualifyingStreetQuery(int $minSales, ?string $outcodeFilter, int $shards = 1, int $shard = 0)
    {
        $outcodeExpression = $this->outcodeExpression();

        $query = DB::table('land_registry')
            ->selectRaw('TRIM("Street") as street')
            ->selectRaw($outcodeExpression.' as outcode')
            ->selectRaw('COUNT(*) as sales_count')
            ->where('PPDCategoryType', 'A')
            ->whereNotNull('Street')
            ->whereRaw('TRIM("Street") <> ?', [''])
            ->whereNotNull('Postcode')
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

    private function normalizeOutcode(string $outcode): ?string
    {
        $normalized = strtoupper(preg_replace('/\s+/', '', trim($outcode)) ?? '');

        return $normalized !== '' ? $normalized : null;
    }
}

    private function shardExpression(string $outcodeExpression): string
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            return 'MOD(ABS(HASHTEXT('.$outcodeExpression.' || \'|\' || TRIM("Street"))), ?)';
        }

        return 'MOD(CRC32(CONCAT('.$outcodeExpression.', \'|\', TRIM("Street"))), ?)';
    }