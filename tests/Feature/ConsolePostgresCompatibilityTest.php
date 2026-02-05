<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ConsolePostgresCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasColumn('land_registry', 'YearDate')) {
            Schema::table('land_registry', function (Blueprint $table): void {
                $table->unsignedSmallInteger('YearDate')->nullable();
            });
        }
    }

    public function test_pcl_warm_runs_with_postgres_safe_sql(): void
    {
        $this->artisan('pcl:warm --parallel=1')->assertExitCode(0);
    }

    public function test_upcl_warm_runs_with_postgres_safe_sql(): void
    {
        $this->artisan('upcl:warm --parallel=1')->assertExitCode(0);
    }

    public function test_outer_prime_warm_runs_with_postgres_safe_sql(): void
    {
        $this->artisan('app:outer-prime-warm --parallel=1')->assertExitCode(0);
    }

    public function test_epc_warmer_runs_with_postgres_safe_sql(): void
    {
        $this->artisan('epc:warm-dashboard')->assertExitCode(0);
    }

    public function test_import_scotland_uses_database_agnostic_import_path(): void
    {
        $directory = storage_path('framework/testing/scotland-epc');
        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $file = $directory.'/sample.csv';
        file_put_contents(
            $file,
            "REPORT_REFERENCE_NUMBER,POSTCODE,LODGEMENT_DATE\n".
            "Readable Header,Readable Header,Readable Header\n".
            "RRN-1,EH1 1AA,2024-01-15\n"
        );

        $this->artisan("epc:import-scotland {$directory}")->assertExitCode(0);

        $this->assertDatabaseHas('epc_certificates_scotland', [
            'REPORT_REFERENCE_NUMBER' => 'RRN-1',
            'POSTCODE' => 'EH1 1AA',
            'LODGEMENT_DATE' => '2024-01-15',
        ]);
    }
}
