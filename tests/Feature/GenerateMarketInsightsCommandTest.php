<?php

namespace Tests\Feature;

use App\Models\MarketInsight;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GenerateMarketInsightsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_generates_market_insights_and_skips_duplicates(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('GenerateMarketInsights command test only runs on PostgreSQL.');
        }

        Carbon::setTestNow(Carbon::create(2026, 3, 9, 12));

        $this->ensureLandRegistryColumnsExist();
        $this->ensureHpiMonthlyTableExists();

        DB::table('land_registry')->insert([
            $this->landRegistryRow('seed-1', 'NW8', 200000, '2024-02-10', 'A'),
            $this->landRegistryRow('seed-2', 'NW8', 200000, '2024-06-15', 'A'),
            $this->landRegistryRow('seed-3', 'NW8', 240000, '2025-03-12', 'A'),
            $this->landRegistryRow('seed-4', 'NW8', 240000, '2025-07-25', 'A'),
            $this->landRegistryRow('seed-7', 'M1', 300000, '2024-02-15', 'A'),
            $this->landRegistryRow('seed-8', 'M1', 305000, '2025-02-15', 'A'),
            $this->landRegistryRow('seed-5', 'AB1', 250000, '2024-01-10', 'B'),
            $this->landRegistryRow('seed-6', 'AB1', 320000, '2026-01-31', 'B'),
        ]);

        DB::table('hpi_monthly')->insert([
            $this->hpiRow('2024-06-01', 100.0),
            $this->hpiRow('2025-06-01', 102.0),
        ]);

        for ($i = 0; $i < 23; $i++) {
            DB::table('land_registry')->insert(
                $this->landRegistryRow("nw8-2024-{$i}", 'NW8', 205000, '2024-08-01', 'A')
            );
        }

        for ($i = 0; $i < 18; $i++) {
            DB::table('land_registry')->insert(
                $this->landRegistryRow("nw8-2025-{$i}", 'NW8', 242000, '2025-09-01', 'A')
            );
        }

        for ($i = 0; $i < 20; $i++) {
            DB::table('land_registry')->insert(
                $this->landRegistryRow("sw1a-2024-{$i}", 'SW1A 1AA', 400000, '2024-05-01', 'A')
            );
        }

        for ($i = 0; $i < 20; $i++) {
            DB::table('land_registry')->insert(
                $this->landRegistryRow("sw1a-2025-{$i}", 'SW1A 1AA', 460000, '2025-05-01', 'A')
            );
        }

        for ($i = 0; $i < 29; $i++) {
            DB::table('land_registry')->insert(
                $this->landRegistryRow("m1-2024-{$i}", 'M1', 301000, '2024-08-01', 'A')
            );
        }

        for ($i = 0; $i < 19; $i++) {
            DB::table('land_registry')->insert(
                $this->landRegistryRow("m1-2025-{$i}", 'M1', 306000, '2025-09-01', 'A')
            );
        }

        $this->artisan('insights:generate')
            ->expectsOutput('Generated 4 insights.')
            ->expectsOutput('Skipped 0 duplicates.')
            ->assertExitCode(0);

        $this->assertDatabaseHas('market_insights', [
            'area_type' => 'postcode',
            'area_code' => 'NW8',
            'insight_type' => 'price_spike',
            'transactions' => 20,
        ]);

        $this->assertDatabaseHas('market_insights', [
            'area_type' => 'postcode',
            'area_code' => 'M1',
            'insight_type' => 'demand_collapse',
            'transactions' => 20,
        ]);

        $this->assertDatabaseHas('market_insights', [
            'area_type' => 'postcode_sector',
            'area_code' => 'SW1A',
            'insight_type' => 'sector_outperformance',
            'transactions' => 20,
        ]);

        $this->assertDatabaseHas('market_insights', [
            'area_type' => 'postcode_sector',
            'area_code' => 'NW8',
            'insight_type' => 'sector_outperformance',
            'transactions' => 20,
        ]);

        $this->assertDatabaseMissing('market_insights', [
            'area_code' => 'AB1',
            'insight_type' => 'price_spike',
        ]);

        $this->assertSame(4, DB::table('market_insights')->count());

        $priceSpike = MarketInsight::query()
            ->where('area_code', 'NW8')
            ->where('insight_type', 'price_spike')
            ->firstOrFail();

        $this->assertSame('postcode', $priceSpike->area_type);
        $this->assertSame('Average property prices in NW8 increased 18.2% year-on-year based on 20 recorded sales.', $priceSpike->insight_text);
        $this->assertSame(18.18, (float) $priceSpike->metric_value);
        $this->assertSame('2025-02-01 00:00:00', $priceSpike->period_start->format('Y-m-d H:i:s'));
        $this->assertSame('2026-01-31 00:00:00', $priceSpike->period_end->format('Y-m-d H:i:s'));
        $this->assertSame('NW8', $priceSpike->supporting_data['area_code']);
        $this->assertSame(20, $priceSpike->supporting_data['sales']);

        $demandCollapse = MarketInsight::query()
            ->where('area_code', 'M1')
            ->where('insight_type', 'demand_collapse')
            ->firstOrFail();

        $this->assertSame('postcode', $demandCollapse->area_type);
        $this->assertSame('Property transactions in M1 fell 33.3% compared with the previous year based on 20 recorded transactions.', $demandCollapse->insight_text);
        $this->assertSame(-33.33, (float) $demandCollapse->metric_value);
        $this->assertSame('M1', $demandCollapse->supporting_data['area_code']);
        $this->assertSame(20, $demandCollapse->supporting_data['sales']);

        $sectorOutperformance = MarketInsight::query()
            ->where('area_code', 'SW1A')
            ->where('insight_type', 'sector_outperformance')
            ->firstOrFail();

        $this->assertSame('postcode_sector', $sectorOutperformance->area_type);
        $this->assertSame('Average property prices in sector SW1A increased 15.0% versus 2.0% nationally based on 20 recorded sales.', $sectorOutperformance->insight_text);
        $this->assertSame(15.00, (float) $sectorOutperformance->metric_value);
        $this->assertSame('SW1A', $sectorOutperformance->supporting_data['area_code']);
        $this->assertSame(20, $sectorOutperformance->supporting_data['sales']);
        $this->assertSame(2.0, (float) $sectorOutperformance->supporting_data['uk_growth']);

        $this->artisan('insights:generate')
            ->expectsOutput('Generated 0 insights.')
            ->expectsOutput('Skipped 4 duplicates.')
            ->assertExitCode(0);

        $this->assertSame(4, DB::table('market_insights')->count());

        Carbon::setTestNow();
    }

    private function ensureLandRegistryColumnsExist(): void
    {
        if (! Schema::hasTable('land_registry')) {
            Schema::create('land_registry', function (Blueprint $table): void {
                $table->string('TransactionID')->nullable();
                $table->string('Postcode')->nullable();
                $table->unsignedInteger('Price')->nullable();
                $table->date('Date')->nullable();
                $table->string('PPDCategoryType')->nullable();
                $table->string('NewBuild')->nullable();
            });
        }

        foreach (['TransactionID', 'Postcode', 'Price', 'Date', 'PPDCategoryType', 'NewBuild'] as $column) {
            if (! Schema::hasColumn('land_registry', $column)) {
                Schema::table('land_registry', function (Blueprint $table) use ($column): void {
                    match ($column) {
                        'TransactionID' => $table->string('TransactionID')->nullable(),
                        'Postcode' => $table->string('Postcode')->nullable(),
                        'Price' => $table->unsignedInteger('Price')->nullable(),
                        'Date' => $table->date('Date')->nullable(),
                        'PPDCategoryType' => $table->string('PPDCategoryType')->nullable(),
                        'NewBuild' => $table->string('NewBuild')->nullable(),
                    };
                });
            }
        }
    }

    private function ensureHpiMonthlyTableExists(): void
    {
        if (! Schema::hasTable('hpi_monthly')) {
            Schema::create('hpi_monthly', function (Blueprint $table): void {
                $table->string('AreaCode', 12);
                $table->date('Date');
                $table->string('RegionName', 100)->nullable();
                $table->decimal('Index', 10, 3)->nullable();
            });
        }

        foreach (['AreaCode', 'Date', 'RegionName', 'Index'] as $column) {
            if (! Schema::hasColumn('hpi_monthly', $column)) {
                Schema::table('hpi_monthly', function (Blueprint $table) use ($column): void {
                    match ($column) {
                        'AreaCode' => $table->string('AreaCode', 12)->nullable(),
                        'Date' => $table->date('Date')->nullable(),
                        'RegionName' => $table->string('RegionName', 100)->nullable(),
                        'Index' => $table->decimal('Index', 10, 3)->nullable(),
                    };
                });
            }
        }
    }

    /**
     * @return array<string, int|string>
     */
    private function landRegistryRow(string $transactionId, string $postcode, int $price, string $date, string $category): array
    {
        return [
            'TransactionID' => $transactionId,
            'Postcode' => $postcode,
            'Price' => $price,
            'Date' => $date,
            'PPDCategoryType' => $category,
            'NewBuild' => 'N',
        ];
    }

    /**
     * @return array<string, float|string>
     */
    private function hpiRow(string $date, float $index): array
    {
        return [
            'AreaCode' => 'K02000001',
            'Date' => $date,
            'RegionName' => 'United Kingdom',
            'Index' => $index,
        ];
    }
}
