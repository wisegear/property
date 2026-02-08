<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HpiDashboardControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureHpiMonthlyTableExists();
    }

    public function test_hpi_dashboard_index_renders_with_postgres_safe_percent_columns(): void
    {
        $this->seedHpiRows();

        $response = $this->get(route('hpi.home', absolute: false));

        $response->assertOk();
        $response->assertSee('HPI Dashboard');
        $response->assertSee('United Kingdom');
        $response->assertSee('England');
    }

    public function test_hpi_overview_renders_with_postgres_safe_percent_columns(): void
    {
        $this->seedHpiRows();

        $response = $this->get(route('hpi.overview', absolute: false));

        $response->assertOk();
        $response->assertSee('UK House Price Index');
        $response->assertSee('assets/images/site/hpi.jpg', false);
    }

    protected function seedHpiRows(): void
    {
        $rows = [
            ['AreaCode' => 'K02000001', 'RegionName' => 'United Kingdom'],
            ['AreaCode' => 'E92000001', 'RegionName' => 'England'],
            ['AreaCode' => 'S92000003', 'RegionName' => 'Scotland'],
            ['AreaCode' => 'W92000004', 'RegionName' => 'Wales'],
            ['AreaCode' => 'N92000002', 'RegionName' => 'Northern Ireland'],
            ['AreaCode' => 'E06000001', 'RegionName' => 'Hartlepool'],
        ];

        foreach ($rows as $row) {
            DB::table('hpi_monthly')->insert([
                'AreaCode' => $row['AreaCode'],
                'Date' => '2025-11-01',
                'RegionName' => $row['RegionName'],
                'AveragePrice' => 250000,
                'Index' => 100.000,
                'SalesVolume' => 100,
                'DetachedPrice' => 350000,
                'SemiDetachedPrice' => 280000,
                'TerracedPrice' => 230000,
                'FlatPrice' => 200000,
                '1m%Change' => 0.4,
                '12m%Change' => 1.2,
            ]);
        }

        DB::table('hpi_monthly')->insert([
            'AreaCode' => 'K02000001',
            'Date' => '2025-10-01',
            'RegionName' => 'United Kingdom',
            'AveragePrice' => 248000,
            'Index' => 99.800,
            'SalesVolume' => 98,
            'DetachedPrice' => 348000,
            'SemiDetachedPrice' => 278000,
            'TerracedPrice' => 228000,
            'FlatPrice' => 198000,
            '1m%Change' => 0.2,
            '12m%Change' => 1.0,
        ]);
    }

    protected function ensureHpiMonthlyTableExists(): void
    {
        if (! Schema::hasTable('hpi_monthly')) {
            Schema::create('hpi_monthly', function (Blueprint $table) {
                $table->string('AreaCode', 12);
                $table->date('Date');
                $table->string('RegionName', 100)->nullable();
                $table->decimal('AveragePrice', 12, 2)->nullable();
                $table->decimal('Index', 10, 3)->nullable();
                $table->decimal('1m%Change', 7, 4)->nullable();
                $table->decimal('12m%Change', 7, 4)->nullable();
                $table->unsignedInteger('SalesVolume')->nullable();
                $table->decimal('DetachedPrice', 12, 2)->nullable();
                $table->decimal('SemiDetachedPrice', 12, 2)->nullable();
                $table->decimal('TerracedPrice', 12, 2)->nullable();
                $table->decimal('FlatPrice', 12, 2)->nullable();
            });
        }
    }
}
