<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BuildEpcPostcodeIndexCommandTest extends TestCase
{
    public function test_command_builds_postcode_index_json_for_both_epc_tables(): void
    {
        $this->ensureEpcTablesExist();
        $outputPath = public_path('data/epc-postcodes.json');

        if (File::exists($outputPath)) {
            File::delete($outputPath);
        }

        File::ensureDirectoryExists(public_path('data'));

        Schema::disableForeignKeyConstraints();
        try {
            DB::table('epc_certificates')->delete();
            DB::table('epc_certificates_scotland')->delete();
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        $ewRows = [];
        for ($i = 1; $i <= 30; $i++) {
            $ewRows[] = [
                'POSTCODE' => 'SW7 5PH',
                'INSPECTION_DATE' => '2016-01-01',
            ];
        }
        for ($i = 1; $i <= 29; $i++) {
            $ewRows[] = [
                'POSTCODE' => 'WR5 3EU',
                'INSPECTION_DATE' => '2017-01-01',
            ];
        }
        for ($i = 0; $i < 35; $i++) {
            $ewRows[] = [
                'POSTCODE' => 'B1 1AA',
                'INSPECTION_DATE' => '2014-01-01',
            ];
        }
        DB::table('epc_certificates')->insert($ewRows);
        DB::table('epc_certificates')->insert([
            ['POSTCODE' => null, 'INSPECTION_DATE' => '2018-01-01'],
        ]);

        $scotlandRows = [];
        for ($i = 1; $i <= 31; $i++) {
            $scotlandRows[] = [
                'POSTCODE' => 'AB10 1AA',
                'INSPECTION_DATE' => '2018-02-02',
            ];
        }
        for ($i = 1; $i <= 30; $i++) {
            $scotlandRows[] = [
                'POSTCODE' => 'KA7 3XY',
                'INSPECTION_DATE' => '2015-01-01',
            ];
        }
        for ($i = 0; $i < 35; $i++) {
            $scotlandRows[] = [
                'POSTCODE' => 'EH1 1YZ',
                'INSPECTION_DATE' => '2012-12-12',
            ];
        }
        DB::table('epc_certificates_scotland')->insert($scotlandRows);
        DB::table('epc_certificates_scotland')->insert([
            ['POSTCODE' => null, 'INSPECTION_DATE' => '2020-01-01'],
        ]);

        $this->artisan('epc:build-postcode-index')->assertExitCode(0);

        $this->assertFileExists($outputPath);
        $payload = json_decode((string) file_get_contents($outputPath), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(['SW7 5PH'], $payload['postcodes']['england_wales']);
        $this->assertSame(['AB10 1AA', 'KA7 3XY'], $payload['postcodes']['scotland']);
        $this->assertSame(30, $payload['meta']['min_certificates']);
        $this->assertSame(2015, $payload['meta']['from_year']);
        $this->assertArrayHasKey('generated_at', $payload['meta']);
        $this->assertNotFalse(strtotime((string) $payload['meta']['generated_at']));
    }

    private function ensureEpcTablesExist(): void
    {
        if (! Schema::hasTable('epc_certificates')) {
            Schema::create('epc_certificates', function (Blueprint $table): void {
                $table->string('POSTCODE', 16)->nullable();
                $table->string('INSPECTION_DATE', 32)->nullable();
            });
        }

        if (! Schema::hasColumn('epc_certificates', 'POSTCODE')) {
            Schema::table('epc_certificates', function (Blueprint $table): void {
                $table->string('POSTCODE', 16)->nullable();
            });
        }
        if (! Schema::hasColumn('epc_certificates', 'INSPECTION_DATE')) {
            Schema::table('epc_certificates', function (Blueprint $table): void {
                $table->string('INSPECTION_DATE', 32)->nullable();
            });
        }
        if (! Schema::hasTable('epc_certificates_scotland')) {
            Schema::create('epc_certificates_scotland', function (Blueprint $table): void {
                $table->string('POSTCODE', 16)->nullable();
                $table->string('INSPECTION_DATE', 32)->nullable();
            });
        }

        if (! Schema::hasColumn('epc_certificates_scotland', 'POSTCODE')) {
            Schema::table('epc_certificates_scotland', function (Blueprint $table): void {
                $table->string('POSTCODE', 16)->nullable();
            });
        }
        if (! Schema::hasColumn('epc_certificates_scotland', 'INSPECTION_DATE')) {
            Schema::table('epc_certificates_scotland', function (Blueprint $table): void {
                $table->string('INSPECTION_DATE', 32)->nullable();
            });
        }
    }
}
