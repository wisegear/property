<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportOnsud extends Command
{
    protected $signature = 'onsud:import {path : Path to ONSUD Data folder} {--truncate : Truncate the onsud table before import}';

    protected $description = 'Bulk import ONSUD regional CSV files into the onsud table in a deterministic order';

    public function handle(): int
    {
        $path = rtrim((string) $this->argument('path'), DIRECTORY_SEPARATOR);

        if (! is_dir($path)) {
            $this->error("Directory not found: {$path}");

            return self::FAILURE;
        }

        if ($this->option('truncate')) {
            DB::table('onsud')->truncate();
            $this->info('Truncated onsud table.');
        }

        $files = glob($path.DIRECTORY_SEPARATOR.'*.csv');
        if (empty($files)) {
            $this->warn("No CSV files found in {$path}");

            return self::SUCCESS;
        }

        $ordered = $this->orderFiles($files);
        $expectedCount = count($ordered);
        $this->info("Found {$expectedCount} CSV file(s) to import.");

        $columns = [
            'UPRN',
            'GRIDGB1E',
            'GRIDGB1N',
            'PCDS',
            'CTY25CD',
            'CED25CD',
            'LAD25CD',
            'WD25CD',
            'PARNCP25CD',
            'HLTH19CD',
            'ctry25cd',
            'RGN25CD',
            'PCON24CD',
            'EER20CD',
            'ttwa15cd',
            'itl25cd',
            'NPARK16CD',
            'OA21CD',
            'lsoa21cd',
            'msoa21cd',
            'WZ11CD',
            'SICBL24CD',
            'BUA24CD',
            'BUASD11CD',
            'ruc21ind',
            'oac21ind',
            'lep21cd1',
            'lep21cd2',
            'pfa23cd',
            'imd19ind',
        ];

        foreach ($ordered as $file) {
            $base = basename($file);
            $this->info("Importing {$base}...");

            try {
                $this->importFileUsingBatchInserts($file, $columns);
            } catch (\Exception $e) {
                $this->error("Failed on {$base}: ".$e->getMessage());

                return self::FAILURE;
            }
        }

        $this->info('All files imported.');

        return self::SUCCESS;
    }

    private function orderFiles(array $files): array
    {
        $order = ['EE', 'EM', 'LN', 'NE', 'NW', 'SC', 'SE', 'SW', 'WA', 'WM', 'YH'];
        $map = [];

        foreach ($files as $file) {
            $base = basename($file);
            if (preg_match('/_([A-Z]{2})\\.csv$/', $base, $m)) {
                $map[$m[1]] = $file;
            } else {
                $map[$base] = $file;
            }
        }

        $ordered = [];
        foreach ($order as $key) {
            if (isset($map[$key])) {
                $ordered[] = $map[$key];
                unset($map[$key]);
            }
        }

        if (! empty($map)) {
            $remaining = array_values($map);
            sort($remaining);
            $ordered = array_merge($ordered, $remaining);
        }

        return $ordered;
    }

    private function importFileUsingBatchInserts(string $file, array $columns): void
    {
        $handle = fopen($file, 'rb');
        if ($handle === false) {
            throw new \RuntimeException("Unable to open CSV: {$file}");
        }

        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);
            throw new \RuntimeException("No header row found in CSV: {$file}");
        }

        $normalizedHeader = [];
        foreach ($header as $columnName) {
            $normalizedHeader[] = $this->stripBom((string) $columnName);
        }

        $headerIndex = array_flip($normalizedHeader);
        $missingColumns = array_diff($columns, array_keys($headerIndex));
        if (! empty($missingColumns)) {
            fclose($handle);
            throw new \RuntimeException(
                'CSV header is missing required columns: '.implode(', ', $missingColumns)
            );
        }

        $batchSize = 1000;
        $batch = [];

        while (($row = fgetcsv($handle)) !== false) {
            if ($row === [null] || $row === []) {
                continue;
            }

            $record = [];
            foreach ($columns as $column) {
                $index = $headerIndex[$column];
                $rawValue = $row[$index] ?? null;
                $record[$column] = $this->normalizeCsvValue($column, $rawValue);
            }

            $batch[] = $record;
            if (count($batch) >= $batchSize) {
                DB::table('onsud')->insert($batch);
                $batch = [];
            }
        }

        fclose($handle);

        if (! empty($batch)) {
            DB::table('onsud')->insert($batch);
        }
    }

    private function normalizeCsvValue(string $column, ?string $value): int|string|null
    {
        if ($value === null || $value === '') {
            return in_array($column, ['GRIDGB1E', 'GRIDGB1N', 'imd19ind'], true) ? null : $value;
        }

        if (in_array($column, ['GRIDGB1E', 'GRIDGB1N', 'imd19ind'], true)) {
            return (int) $value;
        }

        return $value;
    }

    private function stripBom(string $value): string
    {
        return ltrim($value, "\xEF\xBB\xBF");
    }
}
