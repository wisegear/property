<?php

namespace App\Console\Commands;

use App\Services\Property\HighValuePropertyDashboard;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class TopSalesWarm extends Command
{
    protected $signature = 'property:top-sales-warm
                            {--year= : Year of a specific month to warm}
                            {--month= : Month number of a specific month to warm}';

    protected $description = 'Warm the High Value Property dashboard cache';

    /**
     * Execute the console command.
     */
    public function handle(HighValuePropertyDashboard $dashboard): int
    {
        $year = $this->option('year');
        $month = $this->option('month');

        if (($year === null) !== ($month === null)) {
            $this->error('The --year and --month options must be used together.');

            return self::FAILURE;
        }

        if ($year !== null && $month !== null) {
            if ((int) $year !== now()->year) {
                $this->error('The warmer only supports months in the current year.');

                return self::FAILURE;
            }

            $selectedMonth = Carbon::createFromFormat('!Y-n', $year.'-'.$month);

            if (! $dashboard->isAvailable($selectedMonth)) {
                $this->error('No High Value Property data is available for that month.');

                return self::FAILURE;
            }

            $months = [$selectedMonth];
        } else {
            $months = $dashboard->availableMonthsForYear(now()->year);
        }

        if ($months === []) {
            $this->warn('No High Value Property months are available to warm.');

            return self::SUCCESS;
        }

        $this->info('Warming High Value Property dashboard cache...');

        foreach ($months as $selectedMonth) {
            $dashboard->refreshCachedDataFor($selectedMonth);
            $this->info('Warmed '.$selectedMonth->format('F Y'));
        }

        Cache::put('property:high-value:last-warmed-at', now()->toIso8601String(), now()->addDays(2));

        $this->info('High Value Property cache warming complete ('.count($months).' '.str('month')->plural(count($months)).').');

        return self::SUCCESS;
    }
}
