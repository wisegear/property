<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ImportScottishEpc extends Command
{
    protected $signature = 'epc:import-scotland {path : Path to folder containing CSV files} {--scan-only : Scan headers only and do not import} {--write-migration= : Write a generated migration stub to this file path}';

    protected $description = 'Bulk import Scottish EPC quarterly CSV files into epc_certificates_scotland';

    public function handle(): int
    {
        $path = (string) $this->argument('path');

        if (! is_dir($path)) {
            $this->error("Directory not found: {$path}");

            return self::FAILURE;
        }

        $files = glob(rtrim($path, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'*.csv');
        if (empty($files)) {
            $this->warn("No CSV files found in {$path}");

            return self::SUCCESS;
        }

        if ($this->option('scan-only')) {
            $union = [];
            $seen = [];
            $perFile = [];

            foreach ($files as $file) {
                $fh = fopen($file, 'r');
                if ($fh === false) {
                    $this->warn("Cannot open: {$file}");

                    continue;
                }
                $h1 = fgets($fh);
                fgets($fh);
                fclose($fh);
                if ($h1 === false) {
                    $this->warn("No header in: {$file}");

                    continue;
                }
                $cols = str_getcsv(trim($h1));
                $perFile[basename($file)] = $cols;
                foreach ($cols as $c) {
                    if (! isset($seen[$c])) {
                        $seen[$c] = true;
                        $union[] = $c;
                    }
                }
            }

            $this->info('Scanned '.count($perFile).' file(s).');
            $this->info('Union column count: '.count($union));

            // Report columns that vary
            $varying = [];
            foreach ($union as $col) {
                $presentIn = 0;
                foreach ($perFile as $cols) {
                    if (in_array($col, $cols, true)) {
                        $presentIn++;
                    }
                }
                if ($presentIn !== count($perFile)) {
                    $varying[$col] = $presentIn;
                }
            }
            if (! empty($varying)) {
                $this->line('Columns that vary across quarters:');
                foreach ($varying as $col => $cnt) {
                    $this->line(" - {$col}: {$cnt}/".count($perFile));
                }
            }

            if ($path = $this->option('write-migration')) {
                $stub = $this->buildMigrationStub($union);
                if (@file_put_contents($path, $stub) === false) {
                    $this->error("Failed to write migration stub to: {$path}");

                    return self::FAILURE;
                }
                $this->info("Migration stub written to: {$path}");
            }

            return self::SUCCESS;
        }

        $tableColNames = Schema::getColumnListing('epc_certificates_scotland');
        $normalizedTableCols = [];
        foreach ($tableColNames as $name) {
            $normalizedTableCols[$this->stripBom($name)] = $name;
        }

        foreach ($files as $file) {
            $fh = fopen($file, 'r');
            if ($fh === false) {
                $this->error("Unable to open CSV: {$file}");

                return self::FAILURE;
            }

            $header1 = fgets($fh);
            fgets($fh);
            if ($header1 === false) {
                fclose($fh);
                $this->error("No header row in: {$file}");

                return self::FAILURE;
            }
            $columns = array_map(fn ($value) => $this->stripBom((string) $value), str_getcsv(trim($header1)));

            $missing = [];
            $columnMap = [];
            foreach ($columns as $index => $column) {
                $normalized = $this->stripBom($column);
                if (isset($normalizedTableCols[$normalized])) {
                    $columnMap[$index] = $normalizedTableCols[$normalized];
                } else {
                    $missing[] = $column;
                }
            }

            if (! empty($missing)) {
                $this->warn('Skipping unrecognised columns in '.basename($file).':');
                foreach ($missing as $column) {
                    $this->line(' - '.$column);
                }
            }

            $base = basename($file);
            $this->info("Importing {$base}...");
            $inserted = 0;
            $batch = [];
            $batchSize = 1000;

            while (($row = fgetcsv($fh)) !== false) {
                if ($row === [null] || $row === []) {
                    continue;
                }

                $record = ['source_file' => $base];
                foreach ($columnMap as $index => $targetColumn) {
                    $value = $row[$index] ?? null;
                    $record[$targetColumn] = $value === '' ? null : $value;
                }

                $batch[] = $record;
                if (count($batch) >= $batchSize) {
                    DB::table('epc_certificates_scotland')->insert($batch);
                    $inserted += count($batch);
                    $batch = [];
                }
            }

            fclose($fh);

            if (! empty($batch)) {
                DB::table('epc_certificates_scotland')->insert($batch);
                $inserted += count($batch);
            }

            $this->line(" - inserted {$inserted} row(s)");
        }

        $this->info('All files imported.');

        return self::SUCCESS;
    }

    private function buildMigrationStub(array $columns): string
    {
        $stringCols = ['POSTCODE', 'REPORT_REFERENCE_NUMBER', 'LODGEMENT_DATE'];
        $lines = [];
        foreach ($columns as $c) {
            $col = $this->stripBom($c);
            if (in_array($col, $stringCols, true)) {
                $lines[] = '            $'."table->string('{$c}')->nullable();";
            } else {
                $lines[] = '            $'."table->text('{$c}')->nullable();";
            }
        }

        $colsText = implode("\n", $lines);

        return <<<PHP
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('epc_certificates_scotland', function (Blueprint \$table) {
            \$table->id();
{$colsText}
            \$table->string('source_file')->nullable();
            \$table->index('POSTCODE');
            \$table->index('REPORT_REFERENCE_NUMBER');
            \$table->index('LODGEMENT_DATE');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('epc_certificates_scotland');
    }
};
PHP;
    }

    private function stripBom(string $value): string
    {
        return ltrim($value, "\xEF\xBB\xBF");
    }
}
