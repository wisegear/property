<?php

namespace App\Services;

use App\Models\SwapRate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use RuntimeException;
use Throwable;
use ZipArchive;

class SwapRateImporter
{
    private const CURVE_TYPE = 'ois';

    /**
     * @var array<int, int>
     */
    private const TARGET_TERMS = [2, 5, 10];

    public function importLatest(?callable $output = null): array
    {
        $url = (string) config('services.boe.yield_curve_latest_zip_url');

        $this->write($output, 'Downloading latest Bank of England OIS ZIP...', 'line');

        $zipPath = $this->downloadZip($url, 'latest');
        $extractPath = $this->extractZip($zipPath, 'latest');

        try {
            $workbookPath = $this->findWorkbook($extractPath, [
                'ois daily data current month.xlsx',
                'ois daily data',
            ]);

            if ($workbookPath === null) {
                throw new RuntimeException('Could not find the OIS workbook inside the latest ZIP file.');
            }

            $result = $this->importWorkbook($workbookPath, basename($url).'#'.basename($workbookPath));

            $this->write(
                $output,
                sprintf(
                    'Latest import complete. Inserted: %d, updated: %d, parsed rows: %d.',
                    $result['inserted'],
                    $result['updated'],
                    $result['parsed_rows']
                ),
                'info'
            );

            return $result;
        } finally {
            $this->cleanupTemporaryArtifacts($zipPath, $extractPath);
        }
    }

    public function backfillArchive(?callable $output = null): array
    {
        $url = (string) config('services.boe.yield_curve_ois_archive_zip_url');

        $this->write($output, 'Downloading Bank of England OIS archive ZIP...', 'line');

        $zipPath = $this->downloadZip($url, 'archive');
        $extractPath = $this->extractZip($zipPath, 'archive');

        $totals = [
            'inserted' => 0,
            'updated' => 0,
            'parsed_rows' => 0,
            'terms' => [],
            'workbooks' => [],
        ];

        try {
            $workbooks = collect(File::files($extractPath))
                ->filter(fn (\SplFileInfo $file): bool => str_ends_with($file->getFilename(), '.xlsx'))
                ->sortBy(fn (\SplFileInfo $file): string => $file->getFilename(), SORT_NATURAL)
                ->values();

            if ($workbooks->isEmpty()) {
                throw new RuntimeException('Could not find any OIS workbooks inside the archive ZIP file.');
            }

            foreach ($workbooks as $workbook) {
                $result = $this->importWorkbook($workbook->getPathname(), basename($url).'#'.$workbook->getFilename());

                $totals['inserted'] += $result['inserted'];
                $totals['updated'] += $result['updated'];
                $totals['parsed_rows'] += $result['parsed_rows'];
                $totals['terms'] = array_values(array_unique([...$totals['terms'], ...$result['terms']]));
                $totals['workbooks'][] = $workbook->getFilename();

                $this->write(
                    $output,
                    sprintf(
                        'Imported %s. Inserted: %d, updated: %d, parsed rows: %d.',
                        $workbook->getFilename(),
                        $result['inserted'],
                        $result['updated'],
                        $result['parsed_rows']
                    ),
                    'line'
                );
            }

            $latestResult = $this->importLatest($output);
            $totals['inserted'] += $latestResult['inserted'];
            $totals['updated'] += $latestResult['updated'];
            $totals['parsed_rows'] += $latestResult['parsed_rows'];
            $totals['terms'] = array_values(array_unique([...$totals['terms'], ...$latestResult['terms']]));

            $this->write(
                $output,
                sprintf(
                    'Backfill complete. Inserted: %d, updated: %d, parsed rows: %d.',
                    $totals['inserted'],
                    $totals['updated'],
                    $totals['parsed_rows']
                ),
                'info'
            );

            return $totals;
        } finally {
            $this->cleanupTemporaryArtifacts($zipPath, $extractPath);
        }
    }

    /**
     * @return array{
     *     inserted:int,
     *     updated:int,
     *     parsed_rows:int,
     *     terms:array<int, int>
     * }
     */
    private function importWorkbook(string $workbookPath, string $source): array
    {
        $rows = $this->extractRowsFromWorkbook($workbookPath, $source);

        if ($rows === []) {
            return [
                'inserted' => 0,
                'updated' => 0,
                'parsed_rows' => 0,
                'terms' => [],
            ];
        }

        $existing = SwapRate::query()
            ->where('curve_type', self::CURVE_TYPE)
            ->whereIn('term_years', array_values(array_unique(array_column($rows, 'term_years'))))
            ->whereBetween('rate_date', [
                min(array_column($rows, 'rate_date')),
                max(array_column($rows, 'rate_date')),
            ])
            ->get()
            ->keyBy(fn (SwapRate $swapRate): string => $this->compositeKey(
                $swapRate->rate_date->toDateString(),
                $swapRate->curve_type,
                $swapRate->term_years
            ));

        $inserted = 0;
        $updated = 0;

        foreach ($rows as $row) {
            $key = $this->compositeKey($row['rate_date'], $row['curve_type'], $row['term_years']);
            $current = $existing->get($key);

            if ($current === null) {
                $inserted++;

                continue;
            }

            if (
                $this->normalizeDecimal($current->rate) !== $this->normalizeDecimal($row['rate'])
                || $current->source !== $row['source']
            ) {
                $updated++;
            }
        }

        SwapRate::query()->upsert(
            $rows,
            ['rate_date', 'curve_type', 'term_years'],
            ['rate', 'source', 'updated_at']
        );

        $terms = array_values(array_unique(array_column($rows, 'term_years')));
        $this->recalculateDailyChanges($terms);

        return [
            'inserted' => $inserted,
            'updated' => $updated,
            'parsed_rows' => count($rows),
            'terms' => $terms,
        ];
    }

    /**
     * @return array<int, array{
     *     rate_date:string,
     *     curve_type:string,
     *     term_years:int,
     *     rate:string,
     *     daily_change:null,
     *     source:string,
     *     created_at:Carbon,
     *     updated_at:Carbon
     * }>
     */
    private function extractRowsFromWorkbook(string $workbookPath, string $source): array
    {
        $reader = IOFactory::createReaderForFile($workbookPath);
        $reader->setReadDataOnly(true);

        $spreadsheet = $reader->load($workbookPath);

        try {
            $sheet = collect($spreadsheet->getWorksheetIterator())
                ->first(function ($worksheet): bool {
                    $title = mb_strtolower($worksheet->getTitle());

                    return str_contains($title, 'spot curve') && ! str_contains($title, 'short end');
                });

            if ($sheet === null) {
                throw new RuntimeException('Could not find the OIS spot curve worksheet.');
            }

            $termColumns = $this->resolveTermColumns($sheet);
            $rows = [];
            $timestamp = now();

            for ($rowIndex = 5; $rowIndex <= $sheet->getHighestRow(); $rowIndex++) {
                $dateValue = $sheet->getCell([1, $rowIndex])->getValue();
                $rateDate = $this->parseWorkbookDate($dateValue);

                if ($rateDate === null) {
                    continue;
                }

                foreach ($termColumns as $termYears => $columnIndex) {
                    $rateValue = $this->cellNumericValue($sheet->getCell([$columnIndex, $rowIndex]));

                    if (! is_numeric($rateValue)) {
                        continue;
                    }

                    $rows[] = [
                        'rate_date' => $rateDate,
                        'curve_type' => self::CURVE_TYPE,
                        'term_years' => $termYears,
                        'rate' => $this->normalizeDecimal((float) $rateValue),
                        'daily_change' => null,
                        'source' => $source,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ];
                }
            }

            usort($rows, function (array $left, array $right): int {
                return [$left['rate_date'], $left['term_years']] <=> [$right['rate_date'], $right['term_years']];
            });

            return $rows;
        } finally {
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }
    }

    /**
     * @return array<int, int>
     */
    private function resolveTermColumns($sheet): array
    {
        $columns = [];
        $highestColumnIndex = Coordinate::columnIndexFromString($sheet->getHighestColumn());

        for ($columnIndex = 2; $columnIndex <= $highestColumnIndex; $columnIndex++) {
            $rawValue = $this->cellNumericValue($sheet->getCell([$columnIndex, 4]));

            if (! is_numeric($rawValue)) {
                continue;
            }

            $maturity = (float) $rawValue;

            foreach (self::TARGET_TERMS as $termYears) {
                if (abs($maturity - $termYears) < 0.0001) {
                    $columns[$termYears] = $columnIndex;
                }
            }
        }

        if (! isset($columns[2], $columns[5])) {
            throw new RuntimeException('The workbook did not contain the required 2Y and 5Y OIS maturities.');
        }

        return $columns;
    }

    private function parseWorkbookDate(mixed $value): ?string
    {
        if (! is_numeric($value)) {
            return null;
        }

        try {
            return CarbonImmutable::instance(ExcelDate::excelToDateTimeObject((float) $value))
                ->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    private function cellNumericValue($cell): mixed
    {
        $value = $cell->getValue();

        if (is_numeric($value)) {
            return $value;
        }

        try {
            $calculatedValue = $cell->getCalculatedValue();

            if (is_numeric($calculatedValue)) {
                return $calculatedValue;
            }
        } catch (Throwable) {
        }

        return $value;
    }

    /**
     * @param  array<int, int>  $terms
     */
    private function recalculateDailyChanges(array $terms): void
    {
        foreach ($terms as $termYears) {
            $series = SwapRate::query()
                ->where('curve_type', self::CURVE_TYPE)
                ->where('term_years', $termYears)
                ->orderBy('rate_date')
                ->get();

            $previousRate = null;

            foreach ($series as $swapRate) {
                $dailyChange = $previousRate === null
                    ? null
                    : $this->normalizeDecimal((float) $swapRate->rate - $previousRate);

                if ($this->normalizeNullableDecimal($swapRate->daily_change) !== $dailyChange) {
                    $swapRate->daily_change = $dailyChange;
                    $swapRate->save();
                }

                $previousRate = (float) $swapRate->rate;
            }
        }
    }

    private function downloadZip(string $url, string $prefix): string
    {
        Storage::disk('local')->makeDirectory('imports/swaps');

        $response = Http::timeout(120)
            ->retry(2, 1000)
            ->get($url);

        if (! $response->successful()) {
            throw new RuntimeException('Bank of England download failed with status '.$response->status().'.');
        }

        $relativePath = 'imports/swaps/'.$prefix.'-'.now()->format('YmdHis').'-'.bin2hex(random_bytes(4)).'.zip';
        Storage::disk('local')->put($relativePath, $response->body());

        return Storage::disk('local')->path($relativePath);
    }

    private function extractZip(string $zipPath, string $prefix): string
    {
        $extractPath = Storage::disk('local')->path(
            'imports/swaps/'.$prefix.'-extract-'.now()->format('YmdHis').'-'.bin2hex(random_bytes(4))
        );

        File::ensureDirectoryExists($extractPath);

        $zip = new ZipArchive;

        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('Unable to open ZIP archive: '.$zipPath);
        }

        $zip->extractTo($extractPath);
        $zip->close();

        $this->extractNestedZipFiles($extractPath);

        return $extractPath;
    }

    /**
     * @param  array<int, string>  $matchers
     */
    private function findWorkbook(string $extractPath, array $matchers): ?string
    {
        $files = collect(File::allFiles($extractPath))
            ->filter(fn (\SplFileInfo $file): bool => strtolower($file->getExtension()) === 'xlsx')
            ->values();

        foreach ($matchers as $matcher) {
            $normalizedMatcher = strtolower($matcher);

            $exactMatch = $files->first(function (\SplFileInfo $file) use ($normalizedMatcher): bool {
                return strtolower($file->getFilename()) === $normalizedMatcher;
            });

            if ($exactMatch !== null) {
                return $exactMatch->getPathname();
            }
        }

        foreach ($matchers as $matcher) {
            $normalizedMatcher = strtolower($matcher);

            $partialMatch = $files->first(function (\SplFileInfo $file) use ($normalizedMatcher): bool {
                return str_contains(strtolower($file->getFilename()), $normalizedMatcher);
            });

            if ($partialMatch !== null) {
                return $partialMatch->getPathname();
            }
        }

        return null;
    }

    private function extractNestedZipFiles(string $extractPath): void
    {
        $pendingZipPaths = collect(File::allFiles($extractPath))
            ->filter(fn (\SplFileInfo $file): bool => strtolower($file->getExtension()) === 'zip')
            ->map(fn (\SplFileInfo $file): string => $file->getPathname())
            ->values();

        while ($pendingZipPaths->isNotEmpty()) {
            $nestedZipPath = $pendingZipPaths->shift();

            if ($nestedZipPath === null || ! is_file($nestedZipPath)) {
                continue;
            }

            $destination = $nestedZipPath.'-contents';
            File::ensureDirectoryExists($destination);

            $zip = new ZipArchive;

            if ($zip->open($nestedZipPath) !== true) {
                continue;
            }

            $zip->extractTo($destination);
            $zip->close();

            collect(File::allFiles($destination))
                ->filter(fn (\SplFileInfo $file): bool => strtolower($file->getExtension()) === 'zip')
                ->map(fn (\SplFileInfo $file): string => $file->getPathname())
                ->each(function (string $path) use ($pendingZipPaths): void {
                    $pendingZipPaths->push($path);
                });
        }
    }

    private function cleanupTemporaryArtifacts(string $zipPath, string $extractPath): void
    {
        if (is_file($zipPath)) {
            @unlink($zipPath);
        }

        if (is_dir($extractPath)) {
            File::deleteDirectory($extractPath);
        }
    }

    private function compositeKey(string $rateDate, string $curveType, int $termYears): string
    {
        return $rateDate.'|'.$curveType.'|'.$termYears;
    }

    private function normalizeDecimal(float|string|int $value): string
    {
        return number_format((float) $value, 4, '.', '');
    }

    private function normalizeNullableDecimal(float|string|int|null $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return $this->normalizeDecimal($value);
    }

    private function write(?callable $output, string $message, string $method): void
    {
        if ($output !== null) {
            $output($message, $method);
        }
    }
}
