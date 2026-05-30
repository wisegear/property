<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ImportEnglandWalesEpcCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_multiple_yearly_files_and_detects_optional_metadata_rows(): void
    {
        $directory = storage_path('framework/testing/england-wales-epc-import');
        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents(
            $directory.'/certificates-2012.csv',
            "certificate_number,postcode,lodgement_date,ADDRESS,EXTRA_LABEL\n".
            "Readable Header,Readable Header,Readable Header,Readable Header,Readable Header\n".
            "EW-2012,SW1A 1AA,2012-06-01,10 Downing Street,Ignored value\n"
        );

        file_put_contents(
            $directory.'/certificates-2013.csv',
            "certificate_number,postcode,inspection_date,lodgement_date,UPRN\n".
            "EW-2013,SW1A 2AA,2013-07-01,2013-07-02,123456789012\n"
        );

        $this->artisan("epc:import-ew {$directory}")
            ->expectsOutputToContain('Importing certificates-2012.csv...')
            ->expectsOutputToContain('Skipping unrecognised columns in certificates-2012.csv:')
            ->expectsOutputToContain(' - inserted 1 row(s)')
            ->expectsOutputToContain(' - skipped existing 0 row(s)')
            ->expectsOutputToContain(' - total processed 1 row(s)')
            ->expectsOutputToContain('Importing certificates-2013.csv...')
            ->assertExitCode(0);

        $this->assertDatabaseHas('epc_certificates', [
            'LMK_KEY' => 'EW-2012',
            'POSTCODE' => 'SW1A 1AA',
            'LODGEMENT_DATE' => '2012-06-01',
            'ADDRESS' => '10 Downing Street',
            'source_file' => 'certificates-2012.csv',
        ]);

        $this->assertDatabaseHas('epc_certificates', [
            'LMK_KEY' => 'EW-2013',
            'POSTCODE' => 'SW1A 2AA',
            'INSPECTION_DATE' => '2013-07-01',
            'LODGEMENT_DATE' => '2013-07-02',
            'UPRN' => '123456789012',
            'source_file' => 'certificates-2013.csv',
        ]);

        $this->assertSame(2, DB::table('epc_certificates')->count());
    }

    public function test_it_skips_existing_lmk_keys_when_run_repeatedly(): void
    {
        $directory = storage_path('framework/testing/england-wales-epc-repeat');
        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents(
            $directory.'/certificates-2016.csv',
            "certificate_number,postcode,lodgement_date,ADDRESS\n".
            "EW-2016-1,SW1A 5AA,2016-08-03,11 Example Road\n".
            "EW-2016-2,SW1A 6AA,2016-08-04,12 Example Road\n"
        );

        $this->artisan("epc:import-ew {$directory}")
            ->expectsOutputToContain(' - inserted 2 row(s)')
            ->expectsOutputToContain(' - skipped existing 0 row(s)')
            ->expectsOutputToContain(' - total processed 2 row(s)')
            ->assertExitCode(0);

        $this->artisan("epc:import-ew {$directory}")
            ->expectsOutputToContain(' - inserted 0 row(s)')
            ->expectsOutputToContain(' - skipped existing 2 row(s)')
            ->expectsOutputToContain(' - total processed 2 row(s)')
            ->assertExitCode(0);

        $this->assertSame(2, DB::table('epc_certificates')->count());
    }

    public function test_it_maps_lowercase_england_wales_headers_to_existing_table_columns(): void
    {
        $directory = storage_path('framework/testing/england-wales-epc-lowercase-columns');
        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents(
            $directory.'/certificates-2014.csv',
            "certificate_number,postcode,inspection_date,lodgement_date,address1,address2,address3,address,uprn,current_energy_rating,current_energy_efficiency,report_type,low_energy_fixed_lighting_outlets_count,fixed_lighting_outlets_count,uprn_source,country,region\n".
            "EW-2014-1,SW1A 8AA,2014-02-01,2014-02-02,Line 1,Line 2,Line 3,Full Address,555555555555,C,72,RdSAP,8,10,EPC Register,England,London\n"
        );

        $this->artisan("epc:import-ew {$directory}")
            ->expectsOutputToContain('Importing certificates-2014.csv...')
            ->expectsOutputToContain(' - inserted 1 row(s)')
            ->assertExitCode(0);

        $this->assertDatabaseHas('epc_certificates', [
            'LMK_KEY' => 'EW-2014-1',
            'POSTCODE' => 'SW1A 8AA',
            'INSPECTION_DATE' => '2014-02-01',
            'LODGEMENT_DATE' => '2014-02-02',
            'ADDRESS1' => 'Line 1',
            'ADDRESS2' => 'Line 2',
            'ADDRESS3' => 'Line 3',
            'ADDRESS' => 'Full Address',
            'UPRN' => '555555555555',
            'CURRENT_ENERGY_RATING' => 'C',
            'CURRENT_ENERGY_EFFICIENCY' => '72',
            'REPORT_TYPE' => 'RdSAP',
            'LOW_ENERGY_FIXED_LIGHT_COUNT' => '8',
            'FIXED_LIGHTING_OUTLETS_COUNT' => '10',
            'UPRN_SOURCE' => 'EPC Register',
            'COUNTRY' => 'England',
            'REGION' => 'London',
            'source_file' => 'certificates-2014.csv',
        ]);
    }

    public function test_it_fails_fast_when_source_file_column_is_missing(): void
    {
        Schema::table('epc_certificates', function ($table): void {
            $table->dropColumn('source_file');
        });

        $directory = storage_path('framework/testing/england-wales-epc-missing-source-file');
        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents(
            $directory.'/certificates-2017.csv',
            "certificate_number,postcode,lodgement_date\n".
            "EW-2017-1,SW1A 7AA,2017-09-01\n"
        );

        $this->artisan("epc:import-ew {$directory}")
            ->expectsOutputToContain('Column [source_file] is missing from [epc_certificates]. Run the migrations before importing.')
            ->assertExitCode(1);
    }

    public function test_scan_only_reports_union_and_writes_migration_stub_for_missing_columns(): void
    {
        $directory = storage_path('framework/testing/england-wales-epc-scan');
        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $migrationPath = $directory.'/generated_migration.php';

        file_put_contents(
            $directory.'/certificates-2014.csv',
            "certificate_number,postcode,custom_score\n".
            "EW-2014,SW1A 3AA,42\n"
        );

        file_put_contents(
            $directory.'/certificates-2015.csv',
            "certificate_number,postcode,UPRN\n".
            "EW-2015,SW1A 4AA,999999999999\n"
        );

        $this->artisan("epc:import-ew {$directory} --scan-only --write-migration={$migrationPath}")
            ->expectsOutputToContain('CSV files found: 2')
            ->expectsOutputToContain('Union of all columns:')
            ->expectsOutputToContain(' - custom_score')
            ->expectsOutputToContain('Columns that vary between years:')
            ->expectsOutputToContain('Migration stub written to: '.$migrationPath)
            ->assertExitCode(0);

        $this->assertFileExists($migrationPath);
        $this->assertStringContainsString("\$table->text('custom_score')->nullable();", file_get_contents($migrationPath));
        $this->assertSame(0, DB::table('epc_certificates')->count());
    }
}
