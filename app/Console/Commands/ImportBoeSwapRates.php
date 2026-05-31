<?php

namespace App\Console\Commands;

use App\Services\SwapRateImporter;
use Illuminate\Console\Command;
use Throwable;

class ImportBoeSwapRates extends Command
{
    protected $signature = 'swaps:import-boe';

    protected $description = 'Import the latest Bank of England SONIA/OIS swap curve data';

    public function __construct(private SwapRateImporter $swapRateImporter)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Starting Bank of England swap rate import...');

        try {
            $result = $this->swapRateImporter->importLatest(
                fn (string $message, string $method): mixed => $this->{$method}($message)
            );

            $this->info(sprintf(
                'Swap import finished. Inserted %d row(s), updated %d row(s), parsed %d row(s).',
                $result['inserted'],
                $result['updated'],
                $result['parsed_rows']
            ));

            if ($result['inserted'] === 0 && $result['updated'] === 0) {
                $this->line('No new Bank of England swap rows were available. Existing data was left untouched.');
            }

            return self::SUCCESS;
        } catch (Throwable $throwable) {
            $this->error('Swap import failed: '.$throwable->getMessage());

            return self::FAILURE;
        }
    }
}
