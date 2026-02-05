<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ImportOnsudCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_csv_using_batch_insert_compatible_with_postgres(): void
    {
        $directory = sys_get_temp_dir().'/onsud-import-'.uniqid('', true);
        mkdir($directory);
        $filePath = $directory.'/onsud_EE.csv';

        $headers = [
            'UPRN', 'GRIDGB1E', 'GRIDGB1N', 'PCDS', 'CTY25CD', 'CED25CD', 'LAD25CD', 'WD25CD',
            'PARNCP25CD', 'HLTH19CD', 'ctry25cd', 'RGN25CD', 'PCON24CD', 'EER20CD', 'ttwa15cd',
            'itl25cd', 'NPARK16CD', 'OA21CD', 'lsoa21cd', 'msoa21cd', 'WZ11CD', 'SICBL24CD',
            'BUA24CD', 'BUASD11CD', 'ruc21ind', 'oac21ind', 'lep21cd1', 'lep21cd2', 'pfa23cd',
            'imd19ind',
        ];

        $row = [
            '100000000001', '', '', 'AB1 2CD', 'CTY', 'CED', 'LAD', 'WD', 'PAR', 'HLTH',
            'E92000001', 'RGN', 'PCON', 'EER', 'TTWA', 'ITL', 'NPARK', 'OA', 'LSOA', 'MSOA',
            'WZ', 'SICBL', 'BUA', 'BUASD', 'RUC', 'OAC', 'LEP1', 'LEP2', 'PFA', '',
        ];

        $handle = fopen($filePath, 'wb');
        fputcsv($handle, $headers);
        fputcsv($handle, $row);
        fclose($handle);

        try {
            $this->artisan('onsud:import', [
                'path' => $directory,
                '--truncate' => true,
            ])->assertExitCode(0);

            $this->assertDatabaseHas('onsud', [
                'UPRN' => '100000000001',
                'PCDS' => 'AB1 2CD',
                'ctry25cd' => 'E92000001',
            ]);

            $this->assertNull(DB::table('onsud')->where('UPRN', '100000000001')->value('GRIDGB1E'));
            $this->assertNull(DB::table('onsud')->where('UPRN', '100000000001')->value('GRIDGB1N'));
            $this->assertNull(DB::table('onsud')->where('UPRN', '100000000001')->value('imd19ind'));
        } finally {
            @unlink($filePath);
            @rmdir($directory);
        }
    }
}
