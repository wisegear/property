<?php

namespace App\Console\Commands;

use App\Services\SwapRateImporter;
use Illuminate\Console\Command;
use Throwable;

class BackfillBoeSwapRates extends Command
{
    protected $signature = 'swaps:backfill-boe';

    protected $description = 'Backfill historical Bank of England SONIA/OIS swap curve data';

    public function __construct(private SwapRateImporter $swapRateImporter)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Starting Bank of England swap backfill...');

        try {
            $result = $this->swapRateImporter->backfillArchive(
                fn (string $message, string $method): mixed => $this->{$method}($message)
            );

            $this->info(sprintf(
                'Swap backfill finished. Inserted %d row(s), updated %d row(s), parsed %d row(s).',
                $result['inserted'],
                $result['updated'],
                $result['parsed_rows']
            ));

            return self::SUCCESS;
        } catch (Throwable $throwable) {
            $this->error('Swap backfill failed: '.$throwable->getMessage());

            return self::FAILURE;
        }
    }
}
