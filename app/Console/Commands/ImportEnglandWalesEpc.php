<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ImportEnglandWalesEpc extends Command
{
    protected $signature = 'epc:import-ew {path : Path to folder containing yearly CSV files} {--scan-only : Scan headers only and do not import} {--write-migration= : Write a generated migration stub to this file path if needed}';

    protected $description = 'Bulk import England and Wales EPC yearly CSV files into epc_certificates';

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

        sort($files, SORT_NATURAL);

        $tableColNames = Schema::getColumnListing('epc_certificates');
        $normalizedTableCols = [];
        foreach ($tableColNames as $name) {
            $normalizedTableCols[$this->stripBom($name)] = $name;
        }

        if ($this->option('scan-only')) {
            $scan = $this->scanFiles($files, array_keys($normalizedTableCols));
            $this->reportScanResults($scan);

            return $this->writeMigrationStubIfNeeded($scan['union'], $normalizedTableCols);
        }

        if (! isset($normalizedTableCols['source_file'])) {
            $this->error('Column [source_file] is missing from [epc_certificates]. Run the migrations before importing.');

            return self::FAILURE;
        }

        $hasUniqueLmkKeyIndex = $this->hasUniqueLmkKeyIndex();
        if (! $hasUniqueLmkKeyIndex) {
            $this->warn('No unique index or constraint found on [epc_certificates.LMK_KEY]. Duplicate protection is disabled for this run.');
        }

        $isPostgres = DB::connection()->getDriverName() === 'pgsql';

        foreach ($files as $file) {
            $header = $this->readHeaderDetails($file, array_keys($normalizedTableCols));
            if ($header === null) {
                return self::FAILURE;
            }

            $columnMap = [];
            $missing = [];
            foreach ($header['columns'] as $index => $column) {
                $targetColumn = $this->resolveTargetColumn($column, $normalizedTableCols);
                if ($targetColumn !== null) {
                    $columnMap[$index] = $targetColumn;
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

            if (empty($columnMap)) {
                $this->warn('No recognised columns found in '.basename($file).'; skipping file.');

                continue;
            }

            $fh = fopen($file, 'r');
            if ($fh === false) {
                $this->error("Unable to open CSV: {$file}");

                return self::FAILURE;
            }

            $base = basename($file);
            $this->info("Importing {$base}...");
            $stats = $isPostgres
                ? $this->importFileWithPostgresCopy($fh, $header['dataStartsAtRow'], $base, $columnMap, $hasUniqueLmkKeyIndex)
                : $this->importFileWithBatchInserts($fh, $header['dataStartsAtRow'], $base, $columnMap, $hasUniqueLmkKeyIndex);

            fclose($fh);

            if ($stats === null) {
                return self::FAILURE;
            }

            $this->line(' - inserted '.$stats['inserted'].' row(s)');
            $this->line(' - skipped existing '.$stats['skippedExisting'].' row(s)');
            $this->line(' - total processed '.$stats['processed'].' row(s)');
            if ($stats['skippedMissingLmkKey'] > 0) {
                $this->line(' - skipped missing LMK_KEY '.$stats['skippedMissingLmkKey'].' row(s)');
            }
        }

        $this->info('All files imported.');

        return self::SUCCESS;
    }

    /**
     * @param  array<int, string>  $files
     * @param  array<int, string>  $knownColumns
     * @return array{
     *     union: array<int, string>,
     *     perFile: array<string, array<int, string>>
     * }
     */
    private function scanFiles(array $files, array $knownColumns): array
    {
        $union = [];
        $seen = [];
        $perFile = [];

        foreach ($files as $file) {
            $header = $this->readHeaderDetails($file, $knownColumns);
            if ($header === null) {
                continue;
            }

            $perFile[basename($file)] = $header['columns'];
            foreach ($header['columns'] as $column) {
                if (! isset($seen[$column])) {
                    $seen[$column] = true;
                    $union[] = $column;
                }
            }
        }

        return [
            'union' => $union,
            'perFile' => $perFile,
        ];
    }

    /**
     * @param  array{union: array<int, string>, perFile: array<string, array<int, string>>}  $scan
     */
    private function reportScanResults(array $scan): void
    {
        $this->info('CSV files found: '.count($scan['perFile']));
        $this->info('Union column count: '.count($scan['union']));
        $this->line('Union of all columns:');
        foreach ($scan['union'] as $column) {
            $this->line(' - '.$column);
        }

        $varying = [];
        foreach ($scan['union'] as $column) {
            $presentIn = 0;
            foreach ($scan['perFile'] as $columns) {
                if (in_array($column, $columns, true)) {
                    $presentIn++;
                }
            }
            if ($presentIn !== count($scan['perFile'])) {
                $varying[$column] = $presentIn;
            }
        }

        if (empty($varying)) {
            $this->info('No columns vary between years.');

            return;
        }

        $this->line('Columns that vary between years:');
        foreach ($varying as $column => $count) {
            $this->line(" - {$column}: {$count}/".count($scan['perFile']));
        }
    }

    /**
     * @param  array<int, string>  $union
     * @param  array<string, string>  $normalizedTableCols
     */
    private function writeMigrationStubIfNeeded(array $union, array $normalizedTableCols): int
    {
        $path = $this->option('write-migration');
        if (! is_string($path) || $path === '') {
            return self::SUCCESS;
        }

        $missingColumns = [];
        foreach ($union as $column) {
            if ($this->resolveTargetColumn($column, $normalizedTableCols) === null) {
                $missingColumns[] = $column;
            }
        }

        if (empty($missingColumns)) {
            $this->info('No migration stub written; all scanned columns already exist on epc_certificates.');

            return self::SUCCESS;
        }

        $stub = $this->buildMigrationStub($missingColumns);
        if (@file_put_contents($path, $stub) === false) {
            $this->error("Failed to write migration stub to: {$path}");

            return self::FAILURE;
        }

        $this->info("Migration stub written to: {$path}");

        return self::SUCCESS;
    }

    /**
     * @param  resource  $handle
     */
    private function skipRows($handle, int $rows): void
    {
        for ($index = 0; $index < $rows; $index++) {
            fgetcsv($handle);
        }
    }

    /**
     * @param  array<int, string>  $knownColumns
     * @return array{columns: array<int, string>, dataStartsAtRow: int}|null
     */
    private function readHeaderDetails(string $file, array $knownColumns): ?array
    {
        $fh = fopen($file, 'r');
        if ($fh === false) {
            $this->warn("Cannot open: {$file}");

            return null;
        }

        $firstRow = fgetcsv($fh);
        $secondRow = fgetcsv($fh);
        fclose($fh);

        if ($firstRow === false || $firstRow === [null] || $firstRow === []) {
            $this->warn("No header in: {$file}");

            return null;
        }

        $knownLookup = array_fill_keys($knownColumns, true);
        $normalizedFirst = $this->normalizeHeaderRow($firstRow);
        $normalizedSecond = $secondRow !== false ? $this->normalizeHeaderRow($secondRow) : [];

        $firstScore = $this->countKnownColumns($normalizedFirst, $knownLookup);
        $secondScore = $this->countKnownColumns($normalizedSecond, $knownLookup);

        $headerColumns = $normalizedFirst;
        $dataStartsAtRow = 2;

        if ($secondScore > $firstScore && $secondScore > 0) {
            $headerColumns = $normalizedSecond;
            $dataStartsAtRow = 3;
        } elseif ($this->shouldSkipMetadataRow($headerColumns, $secondRow === false ? [] : $secondRow)) {
            $dataStartsAtRow = 3;
        }

        return [
            'columns' => $headerColumns,
            'dataStartsAtRow' => $dataStartsAtRow,
        ];
    }

    /**
     * @param  array<int, string>  $headerColumns
     * @param  array<int, string|null>  $row
     */
    private function shouldSkipMetadataRow(array $headerColumns, array $row): bool
    {
        if ($row === [] || $row === [null]) {
            return false;
        }

        $signalColumns = [
            'LMK_KEY',
            'POSTCODE',
            'LODGEMENT_DATE',
            'INSPECTION_DATE',
            'LODGEMENT_DATETIME',
            'BUILDING_REFERENCE_NUMBER',
            'UPRN',
        ];

        $checkedSignals = 0;
        foreach ($headerColumns as $index => $column) {
            $resolvedColumn = $this->resolveHeaderAlias($column);
            if (! in_array($resolvedColumn, $signalColumns, true)) {
                continue;
            }

            $checkedSignals++;
            $value = trim((string) ($row[$index] ?? ''));
            if ($value === '') {
                continue;
            }

            if ($this->valueLooksLikeData($resolvedColumn, $value)) {
                return false;
            }
        }

        if ($checkedSignals === 0) {
            return false;
        }

        return true;
    }

    private function valueLooksLikeData(string $column, string $value): bool
    {
        return match ($column) {
            'LMK_KEY', 'BUILDING_REFERENCE_NUMBER' => (bool) preg_match('/^[A-Z0-9-]{6,}$/i', $value),
            'POSTCODE' => (bool) preg_match('/^[A-Z]{1,2}\d[A-Z\d]?\s*\d[A-Z]{2}$/i', $value),
            'LODGEMENT_DATE', 'INSPECTION_DATE', 'LODGEMENT_DATETIME' => (bool) preg_match('/^\d{4}-\d{2}-\d{2}(?:[ T]\d{2}:\d{2}:\d{2})?$/', $value),
            'UPRN' => ctype_digit($value),
            default => (bool) preg_match('/[A-Z0-9]/i', $value),
        };
    }

    /**
     * @param  array<int, string>  $row
     * @return array<int, string>
     */
    private function normalizeHeaderRow(array $row): array
    {
        return array_map(
            fn ($value) => $this->stripBom(trim((string) $value)),
            $row,
        );
    }

    /**
     * @param  array<int, string>  $columns
     * @param  array<string, true>  $knownLookup
     */
    private function countKnownColumns(array $columns, array $knownLookup): int
    {
        $count = 0;
        foreach ($columns as $column) {
            $resolvedColumn = $this->resolveHeaderAlias($column);
            if (isset($knownLookup[$resolvedColumn])) {
                $count++;
            }
        }

        return $count;
    }

    private function batchSize(int $columnCount): int
    {
        return max(1, min(500, intdiv(65000, max(1, $columnCount))));
    }

    /**
     * @param  resource  $handle
     * @param  array<int, string>  $columnMap
     * @return array{inserted: int, processed: int, skippedExisting: int, skippedMissingLmkKey: int}|null
     */
    private function importFileWithBatchInserts($handle, int $dataStartsAtRow, string $base, array $columnMap, bool $hasUniqueLmkKeyIndex): ?array
    {
        $this->skipRows($handle, $dataStartsAtRow - 1);

        $inserted = 0;
        $processed = 0;
        $skippedExisting = 0;
        $skippedMissingLmkKey = 0;
        $batch = [];
        $insertColumns = array_values(array_unique([...array_values($columnMap), 'source_file']));
        $batchSize = $this->batchSize(count($insertColumns));

        while (($row = fgetcsv($handle)) !== false) {
            if ($row === [null] || $row === []) {
                continue;
            }

            $processed++;

            $record = ['source_file' => $base];
            foreach ($columnMap as $index => $targetColumn) {
                $value = $row[$index] ?? null;
                $record[$targetColumn] = $value === '' ? null : $value;
            }

            $lmkKey = $record['LMK_KEY'] ?? null;
            if (! is_string($lmkKey) || trim($lmkKey) === '') {
                $skippedMissingLmkKey++;

                continue;
            }

            $batch[] = $record;
            if (count($batch) >= $batchSize) {
                $insertedBatch = $this->insertBatch($batch, $hasUniqueLmkKeyIndex);
                $inserted += $insertedBatch;
                $skippedExisting += count($batch) - $insertedBatch;
                $batch = [];
            }
        }

        if (! empty($batch)) {
            $insertedBatch = $this->insertBatch($batch, $hasUniqueLmkKeyIndex);
            $inserted += $insertedBatch;
            $skippedExisting += count($batch) - $insertedBatch;
        }

        return [
            'inserted' => $inserted,
            'processed' => $processed,
            'skippedExisting' => $skippedExisting,
            'skippedMissingLmkKey' => $skippedMissingLmkKey,
        ];
    }

    /**
     * @param  resource  $handle
     * @param  array<int, string>  $columnMap
     * @return array{inserted: int, processed: int, skippedExisting: int, skippedMissingLmkKey: int}|null
     */
    private function importFileWithPostgresCopy($handle, int $dataStartsAtRow, string $base, array $columnMap, bool $hasUniqueLmkKeyIndex): ?array
    {
        $connection = $this->openPgsqlConnection();
        if ($connection === false) {
            $this->error('Unable to open a native PostgreSQL connection for COPY.');

            return null;
        }

        $stagingTable = 'epc_staging_'.bin2hex(random_bytes(6));
        $stageColumns = array_values(array_unique([...array_values($columnMap), 'source_file']));
        $copyColumnsSql = implode(', ', array_map(
            fn (string $column): string => pg_escape_identifier($connection, $column),
            $stageColumns,
        ));
        $quotedStagingTable = pg_escape_identifier($connection, $stagingTable);

        try {
            $createStageSql = sprintf(
                'CREATE UNLOGGED TABLE %s (LIKE %s INCLUDING DEFAULTS)',
                $quotedStagingTable,
                pg_escape_identifier($connection, 'epc_certificates'),
            );

            if (pg_query($connection, $createStageSql) === false) {
                throw new \RuntimeException('Failed to create staging table.');
            }

            $copySql = sprintf(
                'COPY %s (%s) FROM STDIN WITH (FORMAT csv, HEADER true)',
                $quotedStagingTable,
                $copyColumnsSql,
            );

            if (pg_query($connection, $copySql) === false) {
                throw new \RuntimeException('Failed to start COPY.');
            }

            pg_put_line($connection, $this->csvLine($stageColumns));
            $this->skipRows($handle, $dataStartsAtRow - 1);

            while (($row = fgetcsv($handle)) !== false) {
                if ($row === [null] || $row === []) {
                    continue;
                }

                pg_put_line($connection, $this->buildStagingCsvLine($row, $columnMap, $base, $stageColumns));
            }

            if (! pg_end_copy($connection)) {
                throw new \RuntimeException('Failed to finish COPY.');
            }

            $quotedStageColumns = implode(', ', array_map(
                fn (string $column): string => pg_escape_identifier($connection, $column),
                $stageColumns,
            ));
            $conflictClause = $hasUniqueLmkKeyIndex ? 'ON CONFLICT ("LMK_KEY") DO NOTHING' : '';
            $insertResult = pg_query(
                $connection,
                <<<SQL
                WITH stats AS (
                    SELECT
                        COUNT(*)::bigint AS processed_rows,
                        COUNT(*) FILTER (WHERE "LMK_KEY" IS NULL OR BTRIM("LMK_KEY") = '')::bigint AS missing_lmk_rows
                    FROM {$quotedStagingTable}
                ),
                inserted AS (
                    INSERT INTO "epc_certificates" ({$quotedStageColumns})
                    SELECT {$quotedStageColumns}
                    FROM {$quotedStagingTable}
                    WHERE "LMK_KEY" IS NOT NULL
                      AND BTRIM("LMK_KEY") <> ''
                    {$conflictClause}
                    RETURNING 1
                )
                SELECT
                    stats.processed_rows,
                    stats.missing_lmk_rows,
                    COUNT(inserted.*)::bigint AS inserted_rows
                FROM stats
                LEFT JOIN inserted ON true
                GROUP BY stats.processed_rows, stats.missing_lmk_rows
                SQL
            );

            if ($insertResult === false) {
                throw new \RuntimeException('Failed to insert staged EPC rows.');
            }

            $statsRow = pg_fetch_assoc($insertResult);
            $processedRows = (int) ($statsRow['processed_rows'] ?? 0);
            $missingLmkRows = (int) ($statsRow['missing_lmk_rows'] ?? 0);
            $insertedRows = (int) ($statsRow['inserted_rows'] ?? 0);
            $skippedExisting = $hasUniqueLmkKeyIndex ? max(0, $processedRows - $missingLmkRows - $insertedRows) : 0;

            return [
                'inserted' => $insertedRows,
                'processed' => $processedRows,
                'skippedExisting' => $skippedExisting,
                'skippedMissingLmkKey' => $missingLmkRows,
            ];
        } catch (Throwable $throwable) {
            $this->error($throwable->getMessage());

            return null;
        } finally {
            @pg_query($connection, 'DROP TABLE IF EXISTS '.$quotedStagingTable);
            pg_close($connection);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $batch
     */
    private function insertBatch(array $batch, bool $hasUniqueLmkKeyIndex): int
    {
        if ($hasUniqueLmkKeyIndex) {
            return DB::table('epc_certificates')->insertOrIgnore($batch);
        }

        DB::table('epc_certificates')->insert($batch);

        return count($batch);
    }

    /**
     * @param  array<int, string|int|float|null>  $values
     */
    private function csvLine(array $values): string
    {
        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, $values);
        rewind($stream);
        $line = (string) stream_get_contents($stream);
        fclose($stream);

        return $line;
    }

    /**
     * @param  array<int, string|null>  $row
     * @param  array<int, string>  $columnMap
     * @param  array<int, string>  $stageColumns
     */
    private function buildStagingCsvLine(array $row, array $columnMap, string $base, array $stageColumns): string
    {
        $record = ['source_file' => $base];
        foreach ($columnMap as $index => $targetColumn) {
            $value = $row[$index] ?? null;
            $record[$targetColumn] = $value === '' ? null : $value;
        }

        $orderedValues = [];
        foreach ($stageColumns as $column) {
            $orderedValues[] = $record[$column] ?? null;
        }

        return $this->csvLine($orderedValues);
    }

    /**
     * @return resource|false
     */
    private function openPgsqlConnection()
    {
        $config = config('database.connections.pgsql');
        if (! is_array($config)) {
            return false;
        }

        $parts = [];
        foreach (['host', 'port', 'database', 'username', 'password', 'sslmode'] as $key) {
            $value = $config[$key] ?? null;
            if (! is_string($value) || $value === '') {
                continue;
            }

            $connectionKey = match ($key) {
                'database' => 'dbname',
                'username' => 'user',
                default => $key,
            };

            $parts[] = sprintf("%s='%s'", $connectionKey, str_replace(['\\', '\''], ['\\\\', '\\\''], $value));
        }

        $searchPath = $config['search_path'] ?? null;
        if (is_string($searchPath) && $searchPath !== '') {
            $parts[] = sprintf("options='-c search_path=%s'", str_replace(['\\', '\''], ['\\\\', '\\\''], $searchPath));
        }

        return pg_connect(implode(' ', $parts));
    }

    /**
     * @param  array<string, string>  $normalizedTableCols
     */
    private function resolveTargetColumn(string $column, array $normalizedTableCols): ?string
    {
        $resolvedColumn = $this->resolveHeaderAlias($column);

        return $normalizedTableCols[$resolvedColumn] ?? null;
    }

    private function resolveHeaderAlias(string $column): string
    {
        return match ($column) {
            'certificate_number' => 'LMK_KEY',
            'low_energy_fixed_lighting_outlets_count' => 'LOW_ENERGY_FIXED_LIGHT_COUNT',
            default => $this->normalizeColumnCandidate($column),
        };
    }

    private function normalizeColumnCandidate(string $column): string
    {
        if ($column === '') {
            return $column;
        }

        return strtoupper($column);
    }

    private function hasUniqueLmkKeyIndex(): bool
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            $rows = DB::select(
                <<<'SQL'
                SELECT 1
                FROM pg_indexes
                WHERE schemaname = current_schema()
                  AND tablename = 'epc_certificates'
                  AND indexdef ILIKE '%UNIQUE INDEX%'
                  AND indexdef ILIKE '%("LMK_KEY")%'
                LIMIT 1
                SQL
            );

            return $rows !== [];
        }

        if ($driver === 'sqlite') {
            $rows = DB::select(
                <<<'SQL'
                SELECT 1
                FROM sqlite_master
                WHERE type = 'index'
                  AND tbl_name = 'epc_certificates'
                  AND sql LIKE '%UNIQUE%'
                  AND sql LIKE '%"LMK_KEY"%'
                LIMIT 1
                SQL
            );

            return $rows !== [];
        }

        $rows = DB::select(
            <<<'SQL'
            SELECT 1
            FROM information_schema.statistics
            WHERE table_schema = DATABASE()
              AND table_name = 'epc_certificates'
              AND column_name = 'LMK_KEY'
              AND non_unique = 0
            LIMIT 1
            SQL
        );

        return $rows !== [];
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function buildMigrationStub(array $columns): string
    {
        $stringCols = [
            'POSTCODE',
            'LMK_KEY',
            'BUILDING_REFERENCE_NUMBER',
            'LODGEMENT_DATE',
            'INSPECTION_DATE',
            'LODGEMENT_DATETIME',
            'UPRN',
            'REPORT_TYPE',
        ];
        $lines = [];
        foreach ($columns as $column) {
            $type = in_array($column, $stringCols, true) ? 'string' : 'text';
            $lines[] = '            $'."table->{$type}('{$column}')->nullable();";
        }

        $colsText = implode("\n", $lines);

        return <<<PHP
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('epc_certificates', function (Blueprint \$table) {
{$colsText}
        });
    }

    public function down(): void
    {
        Schema::table('epc_certificates', function (Blueprint \$table) {
            //
        });
    }
};
PHP;
    }

    private function stripBom(string $value): string
    {
        return ltrim($value, "\xEF\xBB\xBF");
    }
}
