<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ImportScottishPropertyPricesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_fails_with_a_helpful_message_when_the_fixed_file_is_missing(): void
    {
        $home = sys_get_temp_dir().'/scottish-prices-missing-'.uniqid('', true);
        mkdir($home, 0777, true);

        $previousHome = $_SERVER['HOME'] ?? getenv('HOME') ?: null;

        $_SERVER['HOME'] = $home;
        putenv("HOME={$home}");

        try {
            $expectedPath = $home.'/Downloads/ros.xlsx';

            $this->artisan('scottish-prices:import')
                ->expectsOutput('Starting Scottish property prices import...')
                ->expectsOutput("Using file: {$expectedPath}")
                ->expectsOutput("Import failed. File not found or not readable: {$expectedPath}")
                ->assertExitCode(1);
        } finally {
            $this->restoreHome($previousHome);
            @rmdir($home);
        }
    }

    public function test_it_imports_the_fixed_excel_file_using_upsert_and_skips_empty_rows(): void
    {
        $home = sys_get_temp_dir().'/scottish-prices-import-'.uniqid('', true);
        $downloads = $home.'/Downloads';
        mkdir($downloads, 0777, true);

        $previousHome = $_SERVER['HOME'] ?? getenv('HOME') ?: null;

        $_SERVER['HOME'] = $home;
        putenv("HOME={$home}");

        $filePath = $downloads.'/ros.xlsx';

        try {
            $this->writeWorkbook($filePath, [
                [
                    'Month',
                    'Local authority code',
                    'Volume of residential property sales',
                    'Mean residential property price',
                    'Median residential property price',
                    'Value of residential property sales',
                    'Local authority',
                ],
                [
                    'April 2003',
                    ' S12000033 ',
                    ' 521 ',
                    '71,967',
                    '51,000',
                    '37,495,000',
                    ' Aberdeen City ',
                ],
                ['', '', '', '', '', '', ''],
                [
                    'April 2003',
                    'S12000034',
                    '',
                    '85,699',
                    '72,000',
                    '37,708,000',
                    'Aberdeenshire',
                ],
            ]);

            $this->artisan('scottish-prices:import')
                ->expectsOutput('Starting Scottish property prices import...')
                ->expectsOutput("Using file: {$filePath}")
                ->expectsOutput('Import complete. Imported 2 row(s).')
                ->assertExitCode(0);

            $this->assertDatabaseHas('scottish_property_prices', [
                'month' => 'April 2003',
                'local_authority_code' => 'S12000033',
                'local_authority' => 'Aberdeen City',
                'volume' => 521,
                'mean_residential_property_price' => 71967,
                'median' => 51000,
                'total_value' => 37495000,
            ]);

            $this->assertNull(
                DB::table('scottish_property_prices')
                    ->where('month', 'April 2003')
                    ->where('local_authority_code', 'S12000034')
                    ->value('volume')
            );

            $this->writeWorkbook($filePath, [
                [
                    'Month',
                    'Local authority code',
                    'Volume of residential property sales',
                    'Mean residential property price',
                    'Median residential property price',
                    'Value of residential property sales',
                    'Local authority',
                ],
                [
                    'April 2003',
                    'S12000033',
                    '522',
                    '72,000',
                    '52,000',
                    '37,500,000',
                    'Aberdeen City Updated',
                ],
            ]);

            $this->artisan('scottish-prices:import')
                ->expectsOutput('Starting Scottish property prices import...')
                ->expectsOutput("Using file: {$filePath}")
                ->expectsOutput('Import complete. Imported 1 row(s).')
                ->assertExitCode(0);

            $this->assertSame(2, DB::table('scottish_property_prices')->count());

            $this->assertDatabaseHas('scottish_property_prices', [
                'month' => 'April 2003',
                'local_authority_code' => 'S12000033',
                'local_authority' => 'Aberdeen City Updated',
                'volume' => 522,
                'mean_residential_property_price' => 72000,
                'median' => 52000,
                'total_value' => 37500000,
            ]);
        } finally {
            $this->restoreHome($previousHome);
            @unlink($filePath);
            @rmdir($downloads);
            @rmdir($home);
        }
    }

    public function test_it_clears_scottish_prices_caches_after_a_successful_import(): void
    {
        $home = sys_get_temp_dir().'/scottish-prices-cache-'.uniqid('', true);
        $downloads = $home.'/Downloads';
        mkdir($downloads, 0777, true);

        $previousHome = $_SERVER['HOME'] ?? getenv('HOME') ?: null;

        $_SERVER['HOME'] = $home;
        putenv("HOME={$home}");

        $filePath = $downloads.'/ros.xlsx';

        try {
            DB::table('scottish_property_prices')->insert([
                [
                    'month' => 'February 2026',
                    'local_authority' => 'Aberdeen City',
                    'local_authority_code' => 'S12000033',
                    'volume' => 100,
                    'mean_residential_property_price' => 200000,
                    'median' => 190000,
                    'total_value' => 20000000,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);

            Cache::put('scottish_prices:v2:authorities', ['Aberdeen City'], now()->addDays(45));
            Cache::put('scottish_prices:v2:latest_month', 'February 2026', now()->addDays(45));
            Cache::put('scottish_prices:v2:scotland', ['years' => [2026]], now()->addDays(45));
            Cache::put('scottish_prices:v2:la:'.md5('aberdeen city'), ['years' => [2026]], now()->addDays(45));

            $this->writeWorkbook($filePath, [
                [
                    'Month',
                    'Local authority code',
                    'Volume of residential property sales',
                    'Mean residential property price',
                    'Median residential property price',
                    'Value of residential property sales',
                    'Local authority',
                ],
                [
                    'March 2026',
                    'S12000033',
                    '101',
                    '205000',
                    '195000',
                    '20705000',
                    'Aberdeen City',
                ],
            ]);

            $this->artisan('scottish-prices:import')
                ->expectsOutput('Starting Scottish property prices import...')
                ->expectsOutput("Using file: {$filePath}")
                ->expectsOutput('Import complete. Imported 1 row(s).')
                ->assertExitCode(0);

            $this->assertNull(Cache::get('scottish_prices:v2:authorities'));
            $this->assertNull(Cache::get('scottish_prices:v2:latest_month'));
            $this->assertNull(Cache::get('scottish_prices:v2:scotland'));
            $this->assertNull(Cache::get('scottish_prices:v2:la:'.md5('aberdeen city')));
        } finally {
            $this->restoreHome($previousHome);
            @unlink($filePath);
            @rmdir($downloads);
            @rmdir($home);
        }
    }

    public function test_it_imports_revised_headers_and_updates_quartiles_without_duplicates(): void
    {
        $home = sys_get_temp_dir().'/scottish-prices-revised-'.uniqid('', true);
        mkdir($home.'/Downloads', 0777, true);
        $previousHome = $_SERVER['HOME'] ?? getenv('HOME') ?: null;
        $_SERVER['HOME'] = $home;
        $filePath = $home.'/Downloads/ros.xlsx';

        try {
            $headers = ['', 'Month', 'local_authority', 'local_authority_code', 'median', 'loqwer_quartile', 'upper_quartile', 'volume', 'total_value'];
            $this->writeWorkbook($filePath, [
                $headers,
                ['', 'April 2003', 'Aberdeen City', 'S12000033', '51,000.50', '34,500.25', '85,500.75', '521', '37,494,560'],
                ['', 'May 2003', 'Aberdeen City', 'S12000033', '', '', 'suppressed', '0', '0'],
                ['', '', 'Aberdeen City', 'S12000033', '100', '50', '150', '1', '100'],
            ]);
            $this->artisan('scottish-prices:import')->expectsOutput('Import complete. Imported 2 row(s).')->assertSuccessful();
            $this->assertDatabaseHas('scottish_property_prices', [
                'month' => 'April 2003', 'median' => 51000.50,
                'lower_quartile' => 34500.25, 'upper_quartile' => 85500.75,
                'volume' => 521, 'total_value' => 37494560,
                'mean_residential_property_price' => 71967,
            ]);
            $this->assertDatabaseHas('scottish_property_prices', [
                'month' => 'May 2003', 'mean_residential_property_price' => null,
                'median' => null, 'lower_quartile' => null, 'upper_quartile' => null,
            ]);
            $headers[5] = 'lower_quartile';
            $this->writeWorkbook($filePath, [$headers, ['', 'April 2003', 'Aberdeen City', 'S12000033', '52000', '35000.50', '86000.50', '522', '37500000']]);
            $this->artisan('scottish-prices:import')->assertSuccessful();
            $this->assertSame(2, DB::table('scottish_property_prices')->count());
            $this->assertDatabaseHas('scottish_property_prices', ['month' => 'April 2003', 'lower_quartile' => 35000.50, 'upper_quartile' => 86000.50]);
            $this->get('/property/scottish-prices?local_authority=Aberdeen%20City')
                ->assertOk()
                ->assertViewHas('lowerQuartilePrices', [35000.50])
                ->assertViewHas('upperQuartilePrices', [86000.50])
                ->assertSee('Median and quartile property prices by year');
        } finally {
            $this->restoreHome($previousHome);
            @unlink($filePath);
            @rmdir($home.'/Downloads');
            @rmdir($home);
        }
    }

    /**
     * @param  array<int, array<int, string>>  $rows
     */
    private function writeWorkbook(string $path, array $rows): void
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        foreach ($rows as $rowIndex => $row) {
            foreach ($row as $columnIndex => $value) {
                $cell = Coordinate::stringFromColumnIndex($columnIndex + 1).($rowIndex + 1);
                $sheet->setCellValue($cell, $value);
            }
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($path);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
    }

    private function restoreHome(string|false|null $previousHome): void
    {
        if (is_string($previousHome) && $previousHome !== '') {
            $_SERVER['HOME'] = $previousHome;
            putenv("HOME={$previousHome}");

            return;
        }

        unset($_SERVER['HOME']);
        putenv('HOME');
    }
}
