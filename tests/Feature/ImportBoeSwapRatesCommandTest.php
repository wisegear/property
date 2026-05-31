<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;
use ZipArchive;

class ImportBoeSwapRatesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_imports_latest_swap_rates_and_calculates_daily_changes(): void
    {
        config()->set('services.boe.yield_curve_latest_zip_url', 'https://example.test/latest.zip');

        $zipPath = $this->createLatestZip([
            ['2025-12-09', 4.0000, 4.2000, 4.4000],
            ['2025-12-10', 4.1000, 4.3000, 4.4500],
        ]);

        Http::fake([
            'https://example.test/latest.zip' => Http::response(
                file_get_contents($zipPath),
                200,
                ['Content-Type' => 'application/zip']
            ),
        ]);

        try {
            $this->artisan('swaps:import-boe')
                ->expectsOutput('Starting Bank of England swap rate import...')
                ->expectsOutput('Downloading latest Bank of England OIS ZIP...')
                ->expectsOutput('Latest import complete. Inserted: 6, updated: 0, parsed rows: 6.')
                ->expectsOutput('Swap import finished. Inserted 6 row(s), updated 0 row(s), parsed 6 row(s).')
                ->assertExitCode(0);
        } finally {
            @unlink($zipPath);
        }

        $this->assertSame(6, DB::table('swap_rates')->count());

        $this->assertDatabaseHas('swap_rates', [
            'rate_date' => '2025-12-10',
            'curve_type' => 'ois',
            'term_years' => 2,
            'rate' => 4.1000,
            'daily_change' => 0.1000,
        ]);

        $this->assertDatabaseHas('swap_rates', [
            'rate_date' => '2025-12-10',
            'curve_type' => 'ois',
            'term_years' => 10,
            'rate' => 4.4500,
            'daily_change' => 0.0500,
        ]);
    }

    public function test_command_handles_missing_ten_year_data_gracefully(): void
    {
        config()->set('services.boe.yield_curve_latest_zip_url', 'https://example.test/latest-missing-10y.zip');

        $zipPath = $this->createLatestZip([
            ['2021-11-30', 0.9500, 1.2500, null],
            ['2021-12-01', 1.0000, 1.3000, null],
        ]);

        Http::fake([
            'https://example.test/latest-missing-10y.zip' => Http::response(
                file_get_contents($zipPath),
                200,
                ['Content-Type' => 'application/zip']
            ),
        ]);

        try {
            $this->artisan('swaps:import-boe')
                ->expectsOutput('Starting Bank of England swap rate import...')
                ->expectsOutput('Downloading latest Bank of England OIS ZIP...')
                ->expectsOutput('Latest import complete. Inserted: 4, updated: 0, parsed rows: 4.')
                ->expectsOutput('Swap import finished. Inserted 4 row(s), updated 0 row(s), parsed 4 row(s).')
                ->assertExitCode(0);
        } finally {
            @unlink($zipPath);
        }

        $this->assertSame(4, DB::table('swap_rates')->count());
        $this->assertSame(0, DB::table('swap_rates')->where('term_years', 10)->count());
    }

    /**
     * @param  array<int, array{0:string, 1:float, 2:float, 3:?float}>  $rows
     */
    private function createLatestZip(array $rows): string
    {
        $workbookPath = tempnam(sys_get_temp_dir(), 'boe-swap-workbook-');
        $zipPath = tempnam(sys_get_temp_dir(), 'boe-swap-zip-');

        if ($workbookPath === false || $zipPath === false) {
            $this->fail('Could not allocate temporary files for the swap rate ZIP test.');
        }

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('4. spot curve');

        $sheet->setCellValue('A4', 'years:');
        $sheet->setCellValue('B4', 0.5);
        $sheet->setCellValue('C4', 1.0);
        $sheet->setCellValue('D4', 1.5);
        $sheet->setCellValue('E4', 2.0);
        $sheet->setCellValue('F4', 2.5);
        $sheet->setCellValue('G4', 3.0);
        $sheet->setCellValue('H4', 3.5);
        $sheet->setCellValue('I4', 4.0);
        $sheet->setCellValue('J4', 4.5);
        $sheet->setCellValue('K4', 5.0);
        $sheet->setCellValue('L4', 5.5);
        $sheet->setCellValue('M4', 6.0);
        $sheet->setCellValue('N4', 6.5);
        $sheet->setCellValue('O4', 7.0);
        $sheet->setCellValue('P4', 7.5);
        $sheet->setCellValue('Q4', 8.0);
        $sheet->setCellValue('R4', 8.5);
        $sheet->setCellValue('S4', 9.0);
        $sheet->setCellValue('T4', 9.5);
        $sheet->setCellValue('U4', 10.0);

        foreach ($rows as $index => [$date, $twoYear, $fiveYear, $tenYear]) {
            $rowNumber = $index + 6;
            $sheet->setCellValue('A'.$rowNumber, ExcelDate::PHPToExcel(new \DateTimeImmutable($date)));
            $sheet->setCellValue('E'.$rowNumber, $twoYear);
            $sheet->setCellValue('K'.$rowNumber, $fiveYear);

            if ($tenYear !== null) {
                $sheet->setCellValue('U'.$rowNumber, $tenYear);
            }
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($workbookPath);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::OVERWRITE);
        $zip->addFile($workbookPath, 'OIS daily data current month.xlsx');
        $zip->close();

        @unlink($workbookPath);

        return $zipPath;
    }
}
