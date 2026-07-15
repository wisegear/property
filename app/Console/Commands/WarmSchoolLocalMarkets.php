<?php

namespace App\Console\Commands;

use App\Services\PropertyResearch\SchoolLocalMarketService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class WarmSchoolLocalMarkets extends Command
{
    protected $signature = 'property:school-property-warm
                            {--limit=0 : Limit the number of unique outcodes to warm}
                            {--refresh : Rebuild existing snapshots}';

    protected $description = 'Warm shared local property snapshots used by school pages.';

    public function handle(SchoolLocalMarketService $localMarketService): int
    {
        $limit = max(0, (int) $this->option('limit'));
        $refresh = (bool) $this->option('refresh');
        $postcodes = DB::table('property_school_establishments')
            ->whereNotNull('postcode')
            ->where('postcode', '<>', '')
            ->pluck('postcode')
            ->map(fn ($postcode): ?string => SchoolLocalMarketService::outcode((string) $postcode))
            ->filter()
            ->unique()
            ->sort()
            ->when($limit > 0, fn ($items) => $items->take($limit))
            ->values();

        if ($postcodes->isEmpty()) {
            $this->warn('No school outcodes were found.');

            return self::SUCCESS;
        }

        $this->info('Warming '.number_format($postcodes->count()).' shared school property snapshots...');
        $progress = $this->output->createProgressBar($postcodes->count());
        $failed = 0;

        foreach ($postcodes as $outcode) {
            try {
                $localMarketService->warm($outcode.' 1AA', $refresh);
            } catch (\Throwable $throwable) {
                $failed++;
                $this->newLine();
                $this->error($outcode.': '.$throwable->getMessage());
            }

            $progress->advance();
        }

        $progress->finish();
        $this->newLine(2);
        $this->info('Shared school property snapshots complete. Failed: '.number_format($failed));

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
