<?php

namespace App\Console\Commands;

use App\Services\Property\MonthlyPropertySnapshot;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Process\Process;

class MonthlyPropertySnapshotWarm extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'property:monthly-snapshot-warm
                            {--year= : Warm one specific year}
                            {--month= : Warm one specific month; requires --year}
                            {--parallel=1 : Number of months to warm concurrently (maximum 8)}
                            {--worker : Run as an internal single-month worker}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Warm every available Property Monthly Snapshot cache for 45 days';

    /**
     * Execute the console command.
     */
    public function handle(MonthlyPropertySnapshot $snapshot): int
    {
        $year = $this->option('year');
        $month = $this->option('month');

        if ($month !== null && $year === null) {
            $this->error('The --month option requires --year.');

            return self::FAILURE;
        }

        if ($year !== null && $month !== null) {
            $selectedMonth = Carbon::createFromFormat('!Y-n', $year.'-'.$month);

            if (! $snapshot->isAvailable($selectedMonth)) {
                $this->error('No Property Monthly Snapshot data is available for that month.');

                return self::FAILURE;
            }

            $months = [$selectedMonth];
        } elseif ($year !== null) {
            $months = $snapshot->availableMonthsForYear((int) $year);
        } else {
            $months = $snapshot->availableMonths();
        }

        if ($months === []) {
            $this->warn('No Property Monthly Snapshot months are available to warm.');

            return self::SUCCESS;
        }

        if ($this->option('worker')) {
            if (count($months) !== 1) {
                $this->error('A worker must be given one specific month.');

                return self::FAILURE;
            }

            $snapshot->refreshCachedDataFor($months[0]);

            return self::SUCCESS;
        }

        $parallel = min(8, max(1, (int) $this->option('parallel')));

        $this->info('Warming Property Monthly Snapshot caches...');
        if ($parallel > 1 && count($months) > 1) {
            if (! $this->warmInParallel($months, $parallel)) {
                return self::FAILURE;
            }
        } else {
            $this->withProgressBar($months, function (Carbon $selectedMonth) use ($snapshot): void {
                $snapshot->refreshCachedDataFor($selectedMonth);
            });
        }

        $this->newLine(2);
        Cache::put('property:monthly-snapshot:last-warmed-at', now()->toIso8601String(), now()->addDays(45));
        $this->info('Property Monthly Snapshot warming complete ('.count($months).' '.str('month')->plural(count($months)).').');

        return self::SUCCESS;
    }

    /**
     * @param  array<int, Carbon>  $months
     */
    private function warmInParallel(array $months, int $parallel): bool
    {
        $this->info("Running in parallel with up to {$parallel} workers...");

        $progress = $this->output->createProgressBar(count($months));
        $progress->start();

        $queue = $months;
        $running = [];
        $failedMonths = [];

        $startWorker = function (Carbon $selectedMonth) use (&$running): void {
            $monthKey = $selectedMonth->format('Y-m');
            $process = new Process([
                PHP_BINARY,
                base_path('artisan'),
                'property:monthly-snapshot-warm',
                '--year='.$selectedMonth->year,
                '--month='.$selectedMonth->month,
                '--worker',
            ]);
            $process->setTimeout(null);
            $process->disableOutput();
            $process->start();
            $running[$monthKey] = $process;
        };

        while ($queue !== [] && count($running) < $parallel) {
            $startWorker(array_shift($queue));
        }

        while ($running !== []) {
            foreach ($running as $monthKey => $process) {
                if ($process->isRunning()) {
                    continue;
                }

                if (! $process->isSuccessful()) {
                    $failedMonths[] = $monthKey;
                }

                unset($running[$monthKey]);
                $progress->advance();

                if ($queue !== []) {
                    $startWorker(array_shift($queue));
                }
            }

            usleep(100000);
        }

        $progress->finish();

        if ($failedMonths !== []) {
            $this->newLine();
            $this->error('Property Monthly Snapshot warming failed for: '.implode(', ', $failedMonths));

            return false;
        }

        return true;
    }
}
