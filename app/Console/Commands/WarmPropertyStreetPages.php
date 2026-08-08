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

    private const TARGET_TABLE = 'property_street_warm_targets';

    private const WARMED_KEY_TABLE = 'property_street_warmed_keys';

    protected $signature = 'property:street-warm
                            {--min-sales=5 : Minimum Category A sales required for a street+outcode target}
                            {--limit=0 : Limit the number of street+outcode pages to warm (0 = all)}
                            {--outcode= : Only warm a specific outcode}
                            {--refresh : Forget an existing street cache before rebuilding it}
                            {--skip-existing : Skip pages that already have a street cache entry}
                            {--shards=1 : Split the warm into this many deterministic shards}
                            {--shard=0 : Warm only this zero-based shard number}
                            {--profile : Emit detailed per-section query profiling}
                            {--cleanup-stale : Delete stale street-page entries after a successful full national run}
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
        $profile = (bool) $this->option('profile');
        $cleanupStale = (bool) $this->option('cleanup-stale');

        if ($profile) {
            $controller->enableWarmProfiling(fn (string $message) => $this->line($message));
        }

        if ($shard >= $shards) {
            $this->error('The --shard option must be zero-based and less than --shards. Example: --shards=4 --shard=0,1,2,3');

            return self::FAILURE;
        }

        if ($cleanupStale && ! $this->isFullNationalRun($limit, $outcodeFilter, $shards, $shard)) {
            $this->error('--cleanup-stale may only be used for an unlimited, unfiltered, unsharded national run.');

            return self::FAILURE;
        }

        $materializeStartedAt = microtime(true);
        $this->materializeTargets($minSales, $outcodeFilter, $shards, $shard, $limit);
        $this->prepareWarmedKeyTable();
        $total = (int) DB::table(self::TARGET_TABLE)->count();
        $materializeElapsed = microtime(true) - $materializeStartedAt;

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
        $this->line(sprintf('Target list materialised once in %.2fs.', $materializeElapsed));
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
        $databaseQueryMilliseconds = 0.0;
        DB::listen(function ($query) use (&$databaseQueryMilliseconds): void {
            $databaseQueryMilliseconds += (float) $query->time;
        });

        $warmed = 0;
        $skipped = 0;
        $failed = 0;

        $processed = 0;
        $lastTargetId = 0;
        $payloadQuerySeconds = 0.0;
        $buildSeconds = 0.0;
        $cacheWriteSeconds = 0.0;
        $refreshedCrimeOutcodes = [];

        while (true) {
            $targets = DB::table(self::TARGET_TABLE)
                ->where('target_id', '>', $lastTargetId)
                ->orderBy('target_id')
                ->limit(self::TARGET_BATCH_SIZE)
                ->get();

            if ($targets->isEmpty()) {
                break;
            }

            $lastTargetId = (int) $targets->last()->target_id;
            $batchTargets = $targets->map(function (object $target): array {
                $street = trim((string) $target->street);

                return [
                    'street' => $street,
                    'street_slug' => Str::slug($street),
                    'outcode' => strtoupper(trim((string) $target->outcode)),
                ];
            })->all();

            $originalBatchCount = count($batchTargets);
            $batchTargets = collect($batchTargets)
                ->unique(fn (array $target): string => PropertyStreetController::cacheKey($target['street_slug'], $target['outcode']))
                ->values()
                ->all();

            $cacheKeys = collect($batchTargets)
                ->map(fn (array $target): string => PropertyStreetController::cacheKey($target['street_slug'], $target['outcode']))
                ->all();
            $previouslySeenKeys = DB::table(self::WARMED_KEY_TABLE)
                ->whereIn('cache_key', $cacheKeys)
                ->pluck('cache_key')
                ->all();

            if ($previouslySeenKeys !== []) {
                $previouslySeenLookup = array_fill_keys($previouslySeenKeys, true);
                $batchTargets = collect($batchTargets)
                    ->reject(fn (array $target): bool => isset($previouslySeenLookup[
                        PropertyStreetController::cacheKey($target['street_slug'], $target['outcode'])
                    ]))
                    ->values()
                    ->all();
                $cacheKeys = collect($batchTargets)
                    ->map(fn (array $target): string => PropertyStreetController::cacheKey($target['street_slug'], $target['outcode']))
                    ->all();
            }

            $skipped += $originalBatchCount - count($batchTargets);

            $targetsToBuild = $batchTargets;
            if (! $refresh) {
                $existing = Cache::many($cacheKeys);
                $targetsToBuild = collect($batchTargets)
                    ->reject(function (array $target) use ($existing): bool {
                        $cacheKey = PropertyStreetController::cacheKey($target['street_slug'], $target['outcode']);

                        return ($existing[$cacheKey] ?? null) !== null;
                    })
                    ->values()
                    ->all();
                $existingCount = count($batchTargets) - count($targetsToBuild);
                $skipped += $skipExisting ? $existingCount : 0;
                $warmed += $skipExisting ? 0 : $existingCount;
            } else {
                foreach (array_unique(array_column($batchTargets, 'outcode')) as $outcode) {
                    if (! isset($refreshedCrimeOutcodes[$outcode])) {
                        Cache::forget(PropertyStreetController::outcodeCrimeCacheKey($outcode));
                        $refreshedCrimeOutcodes[$outcode] = true;
                    }
                }
            }

            try {
                $buildStartedAt = microtime(true);
                $queryMillisecondsBeforeBuild = $databaseQueryMilliseconds;
                $payloads = $controller->buildStreetCacheBatch($targetsToBuild);
                $batchBuildElapsed = microtime(true) - $buildStartedAt;
                $batchQuerySeconds = ($databaseQueryMilliseconds - $queryMillisecondsBeforeBuild) / 1000;
                $payloadQuerySeconds += $batchQuerySeconds;
                $buildSeconds += max(0, $batchBuildElapsed - $batchQuerySeconds);

                if ($payloads !== []) {
                    $cacheWriteStartedAt = microtime(true);
                    $stored = Cache::putMany($payloads, PropertyStreetController::cacheTtl());
                    $cacheWriteSeconds += microtime(true) - $cacheWriteStartedAt;

                    if (! $stored) {
                        throw new \RuntimeException('The cache store rejected the batch write.');
                    }

                    $warmed += count($payloads);
                }

                $missingPayloads = count($targetsToBuild) - count($payloads);
                if ($missingPayloads > 0) {
                    $failed += $missingPayloads;
                } else {
                    $this->recordWarmedKeys($cacheKeys);
                }
            } catch (Throwable $throwable) {
                $failed += count($targetsToBuild);
                $this->newLine();
                $this->error('Failed warming target batch ending at '.$lastTargetId.': '.$throwable->getMessage());
            }

            $processed += $originalBatchCount;

            if (! $noProgress) {
                $this->output->progressAdvance($originalBatchCount);
            } elseif ($processed % self::NO_PROGRESS_LOG_INTERVAL === 0 || $processed === $total) {
                $this->logNoProgressStats(
                    $processed,
                    $total,
                    $warmed,
                    $skipped,
                    $failed,
                    $startedAt,
                    $payloadQuerySeconds,
                    $buildSeconds,
                    $cacheWriteSeconds,
                );
            }

            unset($targets);
        }

        if (! $noProgress) {
            $this->output->progressFinish();
            $this->newLine();
        }

        $staleDeleted = 0;
        if ($failed === 0) {
            Cache::put(
                'property:street:last_warm:min'.$minSales.($outcodeFilter !== null ? ':'.$outcodeFilter : ''),
                now()->toIso8601String(),
                now()->addDays(45)
            );

            if ($cleanupStale) {
                $staleDeleted = $this->deleteStaleStreetCacheEntries();
            }
        }

        $elapsed = round(microtime(true) - $startedAt, 2);
        $this->info("Street page warm complete in {$elapsed}s");
        $this->line('Warmed: '.number_format($warmed));
        $this->line('Skipped: '.number_format($skipped));
        $this->line('Failed: '.number_format($failed));
        $this->line('Stale street cache entries deleted: '.number_format($staleDeleted));
        $this->line('Peak memory: '.$this->formatBytes(memory_get_peak_usage(true)));
        if ($profile) {
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

    private function logNoProgressStats(
        int $processed,
        int $total,
        int $warmed,
        int $skipped,
        int $failed,
        float $startedAt,
        float $querySeconds,
        float $buildSeconds,
        float $cacheWriteSeconds,
    ): void {
        $elapsed = max(microtime(true) - $startedAt, 0.001);
        $rate = $processed / $elapsed;

        $this->info(sprintf(
            '[%s] processed=%s/%s warmed=%s skipped=%s failed=%s rate=%.2f/sec query=%.2fs build=%.2fs cache-write=%.2fs peak-memory=%s',
            now()->format('H:i:s'),
            number_format($processed),
            number_format($total),
            number_format($warmed),
            number_format($skipped),
            number_format($failed),
            $rate,
            $querySeconds,
            $buildSeconds,
            $cacheWriteSeconds,
            $this->formatBytes(memory_get_peak_usage(true)),
        ));
    }

    private function materializeTargets(
        int $minSales,
        ?string $outcodeFilter,
        int $shards,
        int $shard,
        int $limit,
    ): void {
        DB::statement('DROP TABLE IF EXISTS '.self::TARGET_TABLE);

        $query = $this->qualifyingStreetQuery($minSales, $outcodeFilter, $shards, $shard);
        if ($limit > 0) {
            $query->limit($limit);
        }

        DB::statement(
            'CREATE TEMPORARY TABLE '.self::TARGET_TABLE.' AS '
            .'SELECT ROW_NUMBER() OVER (ORDER BY outcode, street) AS target_id, street, outcode, sales_count '
            .'FROM ('.$query->toSql().') AS qualifying_streets',
            $query->getBindings(),
        );
        DB::statement('CREATE UNIQUE INDEX '.self::TARGET_TABLE.'_id_idx ON '.self::TARGET_TABLE.' (target_id)');
    }

    private function prepareWarmedKeyTable(): void
    {
        DB::statement('DROP TABLE IF EXISTS '.self::WARMED_KEY_TABLE);
        DB::statement('CREATE TEMPORARY TABLE '.self::WARMED_KEY_TABLE.' (cache_key VARCHAR(255) PRIMARY KEY)');
    }

    /**
     * @param  array<int, string>  $cacheKeys
     */
    private function recordWarmedKeys(array $cacheKeys): void
    {
        if ($cacheKeys === []) {
            return;
        }

        DB::table(self::WARMED_KEY_TABLE)->insertOrIgnore(
            array_map(fn (string $cacheKey): array => ['cache_key' => $cacheKey], $cacheKeys)
        );
    }

    private function deleteStaleStreetCacheEntries(): int
    {
        if (config('cache.default') !== 'database') {
            $this->warn('Stale cleanup was skipped because the default cache store is not database.');

            return 0;
        }

        $store = config('cache.stores.database');
        $table = (string) ($store['table'] ?? 'cache');
        $prefix = (string) config('cache.prefix', '');
        $pagePrefix = $prefix.PropertyStreetController::streetCacheKeyPrefix();

        return DB::table($table)
            ->where('key', 'like', $pagePrefix.'%')
            ->where('key', 'not like', $pagePrefix.'outcode-comparison:%')
            ->where('key', 'not like', $pagePrefix.'street-slugs:%')
            ->where('key', 'not like', $pagePrefix.'nearby-streets:%')
            ->whereNotExists(function ($query) use ($prefix): void {
                $query->selectRaw('1')
                    ->from(self::WARMED_KEY_TABLE.' as warmed_keys')
                    ->whereRaw('"key" = ? || warmed_keys.cache_key', [$prefix]);
            })
            ->delete();
    }

    private function isFullNationalRun(int $limit, ?string $outcodeFilter, int $shards, int $shard): bool
    {
        return $limit === 0 && $outcodeFilter === null && $shards === 1 && $shard === 0;
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $size = (float) $bytes;
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return number_format($size, 1).' '.$units[$unit];
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
