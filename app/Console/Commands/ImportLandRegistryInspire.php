<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Console\Helper\ProgressBar;
use Throwable;

class ImportLandRegistryInspire extends Command
{
    private const BATCH_SIZE = 5000;

    private const INITIAL_SAMPLE_SIZE = 10;

    protected $signature = 'land-registry:import-inspire
                            {file : Path to the headerless HMLR Price Paid Data to INSPIRE ID lookup CSV}';

    protected $description = 'Replace the Land Registry INSPIRE lookup table from a complete HMLR CSV file';

    public function handle(): int
    {
        $path = (string) $this->argument('file');

        if (! is_file($path) || ! is_readable($path)) {
            $this->error("CSV file does not exist or is not readable: {$path}");

            return self::FAILURE;
        }

        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            $this->error("CSV file could not be opened: {$path}");

            return self::FAILURE;
        }

        if (! $this->hasValidInitialStructure($handle)) {
            fclose($handle);
            $this->error('Initial CSV validation failed. The existing INSPIRE data was left untouched.');

            return self::FAILURE;
        }

        rewind($handle);

        $totalRows = 0;
        $importedRows = 0;
        $skippedRows = 0;
        $progressBar = $this->createProgressBar();

        $this->info("Importing {$path}");

        try {
            DB::connection()->disableQueryLog();

            DB::transaction(function () use ($handle, &$totalRows, &$importedRows, &$skippedRows, $progressBar): void {
                DB::table('land_registry_inspire')->truncate();

                $batch = [];

                while (($row = $this->readCsvRow($handle)) !== false) {
                    $totalRows++;
                    $progressBar?->advance();

                    if ($this->isBlankRow($row)) {
                        $skippedRows++;

                        continue;
                    }

                    $record = $this->validatedRecord($row);

                    if ($record === null) {
                        $skippedRows++;

                        continue;
                    }

                    $batch[] = $record;

                    if (count($batch) === self::BATCH_SIZE) {
                        DB::table('land_registry_inspire')->insert($batch);
                        $importedRows += count($batch);
                        $batch = [];
                    }
                }

                if ($batch !== []) {
                    DB::table('land_registry_inspire')->insert($batch);
                    $importedRows += count($batch);
                }
            });
        } catch (Throwable $throwable) {
            $progressBar?->finish();
            fclose($handle);
            $this->newLine();
            $this->error('Import failed and the replacement was rolled back: '.$throwable->getMessage());

            return self::FAILURE;
        }

        $progressBar?->finish();
        fclose($handle);

        if ($progressBar !== null) {
            $this->newLine(2);
        }

        $finalRowCount = DB::table('land_registry_inspire')->count();

        $this->info('Land Registry INSPIRE import complete.');
        $this->line("Total CSV rows read: {$totalRows}");
        $this->line("Rows successfully imported: {$importedRows}");
        $this->line("Malformed/skipped rows: {$skippedRows}");
        $this->line("Final land_registry_inspire row count: {$finalRowCount}");

        return self::SUCCESS;
    }

    /**
     * @param  resource  $handle
     */
    private function hasValidInitialStructure($handle): bool
    {
        $sampledRows = 0;
        $validRows = 0;

        while ($sampledRows < self::INITIAL_SAMPLE_SIZE && ($row = $this->readCsvRow($handle)) !== false) {
            if ($this->isBlankRow($row)) {
                continue;
            }

            $sampledRows++;

            if ($this->validatedRecord($row) !== null) {
                $validRows++;
            }
        }

        return $sampledRows === self::INITIAL_SAMPLE_SIZE && $validRows === $sampledRows;
    }

    /**
     * @param  resource  $handle
     * @return array<int, string|null>|false
     */
    private function readCsvRow($handle): array|false
    {
        return fgetcsv($handle, null, ',', '"', '');
    }

    /**
     * @param  array<int, string|null>  $row
     */
    private function isBlankRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, string|null>  $row
     * @return array{transaction_id: string, inspire_id: string}|null
     */
    private function validatedRecord(array $row): ?array
    {
        if (count($row) !== 2) {
            return null;
        }

        $transactionId = trim((string) $row[0]);
        $inspireId = trim((string) $row[1]);

        if (preg_match('/^\{[0-9A-Fa-f]{8}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{12}\}$/D', $transactionId) !== 1) {
            return null;
        }

        if (preg_match('/^[0-9]+$/D', $inspireId) !== 1) {
            return null;
        }

        return [
            'transaction_id' => $transactionId,
            'inspire_id' => $inspireId,
        ];
    }

    private function createProgressBar(): ?ProgressBar
    {
        if (! $this->output->isDecorated()) {
            return null;
        }

        $progressBar = $this->output->createProgressBar();
        $progressBar->setRedrawFrequency(self::BATCH_SIZE);
        $progressBar->start();

        return $progressBar;
    }
}
