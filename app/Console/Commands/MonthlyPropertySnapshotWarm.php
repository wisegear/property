<?php

namespace App\Console\Commands;

use App\Services\Property\MonthlyPropertySnapshot;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class MonthlyPropertySnapshotWarm extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'property:monthly-snapshot-warm
                            {--year= : Warm one specific year}
                            {--month= : Warm one specific month; requires --year}';

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

        $this->info('Warming Property Monthly Snapshot caches...');
        $progress = $this->output->createProgressBar(count($months));
        $progress->start();

        foreach ($months as $selectedMonth) {
            $snapshot->refreshCachedDataFor($selectedMonth);
            $progress->advance();
        }

        $progress->finish();
        $this->newLine(2);
        Cache::put('property:monthly-snapshot:last-warmed-at', now()->toIso8601String(), now()->addDays(45));
        $this->info('Property Monthly Snapshot warming complete ('.count($months).' '.str('month')->plural(count($months)).').');

        return self::SUCCESS;
    }
}
