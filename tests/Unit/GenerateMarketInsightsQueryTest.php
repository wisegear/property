<?php

namespace Tests\Unit;

use App\Console\Commands\GenerateMarketInsights;
use App\Services\InsightWriter;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GenerateMarketInsightsQueryTest extends TestCase
{
    public function test_price_trend_rows_only_require_current_growth_for_price_spikes(): void
    {
        $command = $this->makeCommand();

        DB::shouldReceive('select')
            ->once()
            ->withArgs(function (string $sql, array $bindings): bool {
                $this->assertStringContainsString(
                    'AND (100.0 * (current_period.median_price - previous_period.median_price) / NULLIF(previous_period.median_price, 0)) >= ?',
                    $sql
                );
                $this->assertStringNotContainsString(
                    'AND (100.0 * (previous_period.median_price - earlier_period.median_price) / NULLIF(earlier_period.median_price, 0)) >= ?',
                    $sql
                );
                $this->assertSame(15.0, $bindings[array_key_last($bindings)]);
                $this->assertCount(15, $bindings);

                return true;
            })
            ->andReturn([]);

        $this->assertTrue($command->callPriceTrendRows('>=', 15.0, 5)->isEmpty());
    }

    public function test_price_trend_rows_only_require_current_growth_for_price_collapses(): void
    {
        $command = $this->makeCommand();

        DB::shouldReceive('select')
            ->once()
            ->withArgs(function (string $sql, array $bindings): bool {
                $this->assertStringContainsString(
                    'AND (100.0 * (current_period.median_price - previous_period.median_price) / NULLIF(previous_period.median_price, 0)) <= ?',
                    $sql
                );
                $this->assertStringNotContainsString(
                    'AND (100.0 * (previous_period.median_price - earlier_period.median_price) / NULLIF(earlier_period.median_price, 0)) <= ?',
                    $sql
                );
                $this->assertSame(-15.0, $bindings[array_key_last($bindings)]);
                $this->assertCount(15, $bindings);

                return true;
            })
            ->andReturn([]);

        $this->assertTrue($command->callPriceTrendRows('<=', -15.0, 20)->isEmpty());
    }

    public function test_unexpected_hotspot_rows_use_a_fixed_outperformance_margin(): void
    {
        $command = $this->makeCommand();

        DB::shouldReceive('select')
            ->once()
            ->withArgs(function (string $sql, array $bindings): bool {
                $this->assertStringContainsString(
                    'AND (100.0 * (current_period.median_price - previous_period.median_price) / NULLIF(previous_period.median_price, 0)) >= ((100.0 * (uk_current.median_price - uk_previous.median_price) / NULLIF(uk_previous.median_price, 0)) + ?)',
                    $sql
                );
                $this->assertStringContainsString(
                    'AND (100.0 * (previous_period.median_price - earlier_period.median_price) / NULLIF(earlier_period.median_price, 0)) >= ((100.0 * (uk_previous.median_price - uk_earlier.median_price) / NULLIF(uk_earlier.median_price, 0)) + ?)',
                    $sql
                );
                $this->assertStringNotContainsString('2 *', $sql);
                $this->assertSame([20, 20, 20.0, 20.0], array_slice($bindings, -4));

                return true;
            })
            ->andReturn([]);

        $this->assertTrue($command->callUnexpectedHotspotRows()->isEmpty());
    }

    public function test_momentum_reversal_rows_use_tighter_thresholds(): void
    {
        $command = $this->makeCommand();

        DB::shouldReceive('select')
            ->once()
            ->withArgs(function (string $sql, array $bindings): bool {
                $this->assertStringContainsString('AND (100.0 * (previous_period.median_price - earlier_period.median_price) / NULLIF(earlier_period.median_price, 0)) > ?', $sql);
                $this->assertStringContainsString('AND (100.0 * (current_period.median_price - previous_period.median_price) / NULLIF(previous_period.median_price, 0)) < ?', $sql);
                $this->assertSame([20, 25, -20], array_slice($bindings, -3));

                return true;
            })
            ->andReturn([]);

        $this->assertTrue($command->callMomentumReversalRows()->isEmpty());
    }

    public function test_demand_collapse_rows_require_meaningful_current_volume(): void
    {
        $command = $this->makeCommand();

        DB::shouldReceive('select')
            ->once()
            ->withArgs(function (string $sql, array $bindings): bool {
                $this->assertStringContainsString('AND current_period.sales >= 15', $sql);
                $this->assertSame([20, -45.0], array_slice($bindings, -2));

                return true;
            })
            ->andReturn([]);

        $this->assertTrue($command->callDemandCollapseRows()->isEmpty());
    }

    public function test_liquidity_surge_rows_do_not_require_persistence(): void
    {
        $command = $this->makeCommand();

        $this->assertTrue($command->callLiquiditySurgeRows()->isEmpty());
        $this->assertSame([
            'operator' => '>=',
            'threshold' => 70.0,
            'requiresPersistence' => false,
        ], $command->salesChangeCalls[0]);
    }

    public function test_liquidity_stress_rows_require_falling_sales_and_rising_prices(): void
    {
        $command = $this->makeCommand();

        DB::shouldReceive('select')
            ->once()
            ->withArgs(function (string $sql, array $bindings): bool {
                $this->assertStringContainsString(
                    'AND (100.0 * (current_period.sales - previous_period.sales) / NULLIF(previous_period.sales, 0)) <= ?',
                    $sql
                );
                $this->assertStringContainsString(
                    'AND (100.0 * (current_period.median_price - previous_period.median_price) / NULLIF(previous_period.median_price, 0)) >= ?',
                    $sql
                );
                $this->assertSame([20, 20, -40.0, 5.0], array_slice($bindings, -4));

                return true;
            })
            ->andReturn([]);

        $this->assertTrue($command->callLiquidityStressRows()->isEmpty());
    }

    public function test_detect_liquidity_stress_maps_the_new_insight_type(): void
    {
        $command = $this->makeCommand();

        DB::shouldReceive('select')
            ->once()
            ->andReturn([
                (object) [
                    'sector' => 'LS11',
                    'sales' => 22,
                    'previous_sales' => 40,
                    'median_price' => 210000,
                    'previous_median_price' => 200000,
                    'sales_change' => -45.0,
                    'price_growth' => 5.0,
                ],
            ]);

        $result = $command->callDetectLiquidityStress();

        $this->assertCount(1, $result);
        $this->assertSame('postcode_sector', $result[0]['area_type']);
        $this->assertSame('liquidity_stress', $result[0]['insight_type']);
        $this->assertSame('LS11', $result[0]['area_code']);
        $this->assertSame(-45.0, $result[0]['metric_value']);
        $this->assertSame(22, $result[0]['transactions']);
        $this->assertSame(-45.0, $result[0]['supporting_data']['sales_change']);
        $this->assertSame(5.0, $result[0]['supporting_data']['price_growth']);
        $this->assertSame(
            'Property transactions in postcode sector LS11 fell 45.0% between 01 Feb 2025 and 31 Jan 2026 while median prices still rose 5.0%, suggesting weakening market liquidity.',
            $result[0]['insight_text']
        );
    }

    public function test_market_freeze_rows_do_not_require_persistence(): void
    {
        $command = $this->makeCommand();

        $this->assertTrue($command->callMarketFreezeRows()->isEmpty());
        $this->assertSame([
            'operator' => '<=',
            'threshold' => -60.0,
            'requiresPersistence' => false,
        ], $command->salesChangeCalls[0]);
    }

    private function makeCommand(): GenerateMarketInsightsQueryTestCommand
    {
        return new GenerateMarketInsightsQueryTestCommand(new InsightWriter);
    }
}

class GenerateMarketInsightsQueryTestCommand extends GenerateMarketInsights
{
    /**
     * @var array<int, array{operator:string,threshold:float,requiresPersistence:bool}>
     */
    public array $salesChangeCalls = [];

    public function callPriceTrendRows(string $operator, float $threshold, int $minimumSales): Collection
    {
        return $this->priceTrendRows($operator, $threshold, $minimumSales);
    }

    public function callUnexpectedHotspotRows(): Collection
    {
        return $this->unexpectedHotspotRows();
    }

    public function callDemandCollapseRows(): Collection
    {
        return $this->demandCollapseRows();
    }

    public function callMomentumReversalRows(): Collection
    {
        return $this->momentumReversalRows();
    }

    public function callLiquiditySurgeRows(): Collection
    {
        return $this->liquiditySurgeRows();
    }

    public function callLiquidityStressRows(): Collection
    {
        return $this->liquidityStressRows();
    }

    public function callDetectLiquidityStress(): Collection
    {
        return $this->detectLiquidityStress();
    }

    public function callMarketFreezeRows(): Collection
    {
        return $this->marketFreezeRows();
    }

    protected function rollingPeriods(): ?array
    {
        return [
            'current_start' => Carbon::create(2025, 2, 1)->startOfDay(),
            'current_end' => Carbon::create(2026, 1, 31)->startOfDay(),
            'previous_start' => Carbon::create(2024, 2, 1)->startOfDay(),
            'previous_end' => Carbon::create(2025, 1, 31)->startOfDay(),
            'earlier_start' => Carbon::create(2023, 2, 1)->startOfDay(),
            'earlier_end' => Carbon::create(2024, 1, 31)->startOfDay(),
        ];
    }

    protected function column(string $name): string
    {
        return $name;
    }

    protected function minSectorTransactions(): int
    {
        return 20;
    }

    protected function salesChangeRows(string $operator, float $threshold, bool $requiresPersistence = false): Collection
    {
        $this->salesChangeCalls[] = [
            'operator' => $operator,
            'threshold' => $threshold,
            'requiresPersistence' => $requiresPersistence,
        ];

        return collect();
    }
}
