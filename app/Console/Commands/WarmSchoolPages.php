<?php

namespace App\Console\Commands;

use App\Http\Controllers\SchoolController;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class WarmSchoolPages extends Command
{
    private const TARGET_BATCH_SIZE = 250;

    private const NO_PROGRESS_LOG_INTERVAL = 250;

    protected $signature = 'property:school-warm
                            {--limit=0 : Limit the number of school pages to warm (0 = all)}
                            {--urn= : Only warm one school by URN}
                            {--phase= : Only warm schools with this phase code or phase name}
                            {--la= : Only warm schools with this local authority code or name}
                            {--refresh : Forget an existing school page cache before rebuilding it}
                            {--skip-existing : Skip pages that already have a school page cache entry}
                            {--shards=1 : Split the warm into this many deterministic shards}
                            {--shard=0 : Warm only this zero-based shard number}
                            {--no-progress : Disable the progress bar for lower console overhead}';

    protected $description = 'Warm cached payloads for school detail pages.';

    public function handle(SchoolController $controller): int
    {
        if (! Schema::hasTable('property_school_establishments')) {
            $this->error('Missing property_school_establishments table.');

            return self::FAILURE;
        }

        DB::connection()->disableQueryLog();

        $limit = max(0, (int) $this->option('limit'));
        $refresh = (bool) $this->option('refresh');
        $skipExisting = (bool) $this->option('skip-existing');
        $noProgress = (bool) $this->option('no-progress');
        $urnFilter = $this->normalizeOption((string) $this->option('urn'));
        $phaseFilter = $this->normalizeOption((string) $this->option('phase'));
        $localAuthorityFilter = $this->normalizeOption((string) $this->option('la'));
        $shards = max(1, (int) $this->option('shards'));
        $shard = max(0, (int) $this->option('shard'));

        if ($shard >= $shards) {
            $this->error('The --shard option must be zero-based and less than --shards. Example: --shards=4 --shard=0,1,2,3');

            return self::FAILURE;
        }

        $baseQuery = $this->schoolTargetsQuery($urnFilter, $phaseFilter, $localAuthorityFilter, $shards, $shard);
        $total = (int) DB::query()->fromSub(
            $limit > 0 ? (clone $baseQuery)->limit($limit) : $baseQuery,
            'school_targets'
        )->count();

        if ($total === 0) {
            $this->warn('No qualifying school pages found to warm.');

            return self::SUCCESS;
        }

        $this->info('Warming '.$total.' school page caches...');
        if ($urnFilter !== null) {
            $this->line('URN filter: '.$urnFilter);
        }
        if ($phaseFilter !== null) {
            $this->line('Phase filter: '.$phaseFilter);
        }
        if ($localAuthorityFilter !== null) {
            $this->line('Local authority filter: '.$localAuthorityFilter);
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
                $urn = trim((string) ($target->urn ?? ''));
                $name = trim((string) ($target->establishment_name ?? ''));

                try {
                    $cacheKey = SchoolController::showCacheKey($urn);

                    if ($refresh) {
                        $controller->refreshSchoolCache($urn);
                        $warmed++;
                    } elseif ($skipExisting && Cache::has($cacheKey)) {
                        $skipped++;
                    } else {
                        $controller->warmSchoolCache($urn);
                        $warmed++;
                    }
                } catch (Throwable $throwable) {
                    $failed++;
                    $this->newLine();
                    $this->error('Failed warming '.$this->targetLabel($urn, $name).': '.$throwable->getMessage());
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

        Cache::put('property:school:last_warm', now()->toIso8601String(), now()->addDays(45));

        $elapsed = round(microtime(true) - $startedAt, 2);
        $this->info("School page warm complete in {$elapsed}s");
        $this->line('Warmed: '.number_format($warmed));
        $this->line('Skipped: '.number_format($skipped));
        $this->line('Failed: '.number_format($failed));

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function schoolTargetsQuery(?string $urnFilter, ?string $phaseFilter, ?string $localAuthorityFilter, int $shards, int $shard)
    {
        $query = DB::table('property_school_establishments')
            ->select(['urn', 'establishment_name'])
            ->whereNotNull('urn')
            ->where('urn', '!=', '')
            ->whereNotNull('establishment_name')
            ->where('establishment_name', '!=', '');

        if ($urnFilter !== null) {
            $query->where('urn', $urnFilter);
        }

        if ($phaseFilter !== null) {
            $query->where(function ($query) use ($phaseFilter): void {
                $query
                    ->where('phase_of_education_code', $phaseFilter)
                    ->orWhereRaw('LOWER(phase_of_education_name) = ?', [strtolower($phaseFilter)]);
            });
        }

        if ($localAuthorityFilter !== null) {
            $query->where(function ($query) use ($localAuthorityFilter): void {
                $query
                    ->where('la_code', $localAuthorityFilter)
                    ->orWhereRaw('LOWER(la_name) = ?', [strtolower($localAuthorityFilter)]);
            });
        }

        if ($shards > 1) {
            $query->whereRaw($this->shardExpression().' = ?', [$shards, $shard]);
        }

        return $query
            ->orderBy('urn')
            ->orderBy('establishment_name');
    }

    private function shardExpression(): string
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            return 'MOD(ABS(HASHTEXT(urn)), ?)';
        }

        return 'MOD(CAST(urn AS INTEGER), ?)';
    }

    private function normalizeOption(string $value): ?string
    {
        $value = trim($value);

        return $value !== '' ? $value : null;
    }

    private function targetLabel(string $urn, string $name): string
    {
        if ($name === '') {
            return $urn;
        }

        return $name.' ['.$urn.']';
    }

    private function logNoProgressStats(int $processed, int $warmed, int $skipped, int $failed, float $startedAt): void
    {
        $elapsed = max(microtime(true) - $startedAt, 0.001);
        $rate = $processed / $elapsed;

        $this->info(sprintf(
            '[%s] processed=%s warmed=%s skipped=%s failed=%s rate=%.2f/sec',
            now()->format('H:i:s'),
            number_format($processed),
            number_format($warmed),
            number_format($skipped),
            number_format($failed),
            $rate
        ));
    }
}
