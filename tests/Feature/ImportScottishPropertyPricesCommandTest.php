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
                'volume_of_residential_property_sales' => 521,
                'mean_residential_property_price' => 71967,
                'median_residential_property_price' => 51000,
                'value_of_residential_property_sales' => 37495000,
            ]);

            $this->assertNull(
                DB::table('scottish_property_prices')
                    ->where('month', 'April 2003')
                    ->where('local_authority_code', 'S12000034')
                    ->value('volume_of_residential_property_sales')
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
                'volume_of_residential_property_sales' => 522,
                'mean_residential_property_price' => 72000,
                'median_residential_property_price' => 52000,
                'value_of_residential_property_sales' => 37500000,
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
                    'volume_of_residential_property_sales' => 100,
                    'mean_residential_property_price' => 200000,
                    'median_residential_property_price' => 190000,
                    'value_of_residential_property_sales' => 20000000,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);

            Cache::put('scottish_prices:authorities', ['Aberdeen City'], now()->addDays(45));
            Cache::put('scottish_prices:latest_month', 'February 2026', now()->addDays(45));
            Cache::put('scottish_prices:scotland', ['years' => [2026]], now()->addDays(45));
            Cache::put('scottish_prices:la:'.md5('aberdeen city'), ['years' => [2026]], now()->addDays(45));

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

            $this->assertNull(Cache::get('scottish_prices:authorities'));
            $this->assertNull(Cache::get('scottish_prices:latest_month'));
            $this->assertNull(Cache::get('scottish_prices:scotland'));
            $this->assertNull(Cache::get('scottish_prices:la:'.md5('aberdeen city')));
        } finally {
            $this->restoreHome($previousHome);
            @unlink($filePath);
            @rmdir($downloads);
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
