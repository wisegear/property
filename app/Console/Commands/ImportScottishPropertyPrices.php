<?php

namespace App\Console\Commands;

use App\Imports\ScottishPropertyPricesImport;
use Illuminate\Console\Command;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class ImportScottishPropertyPrices extends Command
{
    protected $signature = 'scottish-prices:import';

    protected $description = 'Import Scottish property price data from ~/Downloads/ros.xlsx';

    public function handle(): int
    {
        $filePath = $this->resolveRosFilePath();

        $this->info('Starting Scottish property prices import...');
        $this->line("Using file: {$filePath}");

        if (! is_file($filePath) || ! is_readable($filePath)) {
            $this->error("Import failed. File not found or not readable: {$filePath}");

            return self::FAILURE;
        }

        try {
            $import = new ScottishPropertyPricesImport;

            Excel::import($import, $filePath);

            $this->info("Import complete. Imported {$import->importedRowCount()} row(s).");

            return self::SUCCESS;
        } catch (Throwable $throwable) {
            $this->error('Import failed: '.$throwable->getMessage());

            return self::FAILURE;
        }
    }

    private function resolveRosFilePath(): string
    {
        return $this->expandTildePath('~/Downloads/ros.xlsx');
    }

    private function expandTildePath(string $path): string
    {
        if (! str_starts_with($path, '~/')) {
            return $path;
        }

        return $this->userHomePath().DIRECTORY_SEPARATOR.ltrim(substr($path, 2), DIRECTORY_SEPARATOR);
    }

    private function userHomePath(): string
    {
        $home = $_SERVER['HOME'] ?? getenv('HOME') ?: null;

        if (is_string($home) && $home !== '') {
            return rtrim($home, DIRECTORY_SEPARATOR);
        }

        $homeDrive = $_SERVER['HOMEDRIVE'] ?? getenv('HOMEDRIVE') ?: '';
        $homePath = $_SERVER['HOMEPATH'] ?? getenv('HOMEPATH') ?: '';

        if ($homeDrive !== '' && $homePath !== '') {
            return rtrim($homeDrive.$homePath, DIRECTORY_SEPARATOR);
        }

        throw new \RuntimeException('Unable to resolve the current user home directory.');
    }
}
