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
                $this->assertSame([20, 20, 12.0, 12.0], array_slice($bindings, -4));

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
                $this->assertSame([20, 15, -10], array_slice($bindings, -3));

                return true;
            })
            ->andReturn([]);

        $this->assertTrue($command->callMomentumReversalRows()->isEmpty());
    }

    public function test_liquidity_surge_rows_do_not_require_persistence(): void
    {
        $command = $this->makeCommand();

        $this->assertTrue($command->callLiquiditySurgeRows()->isEmpty());
        $this->assertSame([
            'operator' => '>=',
            'threshold' => 50.0,
            'requiresPersistence' => false,
        ], $command->salesChangeCalls[0]);
    }

    public function test_market_freeze_rows_do_not_require_persistence(): void
    {
        $command = $this->makeCommand();

        $this->assertTrue($command->callMarketFreezeRows()->isEmpty());
        $this->assertSame([
            'operator' => '<=',
            'threshold' => -50.0,
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

    public function callMomentumReversalRows(): Collection
    {
        return $this->momentumReversalRows();
    }

    public function callLiquiditySurgeRows(): Collection
    {
        return $this->liquiditySurgeRows();
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
