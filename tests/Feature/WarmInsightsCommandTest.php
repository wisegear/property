<?php

namespace Tests\Feature;

use App\Http\Controllers\InsightController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class WarmInsightsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_warms_each_distinct_insight_sector(): void
    {
        DB::table('market_insights')->insert([
            [
                'area_type' => 'postcode',
                'area_code' => 'AL12',
                'insight_type' => 'price_spike',
                'metric_value' => 12.34,
                'transactions' => 25,
                'period_start' => '2025-01-01',
                'period_end' => '2025-12-31',
                'supporting_data' => json_encode(['area_code' => 'AL12'], JSON_THROW_ON_ERROR),
                'insight_text' => 'AL12 insight',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'area_type' => 'postcode_sector',
                'area_code' => 'B1',
                'insight_type' => 'momentum_reversal',
                'metric_value' => -6.5,
                'transactions' => 32,
                'period_start' => '2025-01-01',
                'period_end' => '2025-12-31',
                'supporting_data' => json_encode(['area_code' => 'B1'], JSON_THROW_ON_ERROR),
                'insight_text' => 'B1 insight',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'area_type' => 'postcode',
                'area_code' => 'AL12',
                'insight_type' => 'demand_collapse',
                'metric_value' => -35.1,
                'transactions' => 22,
                'period_start' => '2025-01-01',
                'period_end' => '2025-12-31',
                'supporting_data' => json_encode(['area_code' => 'AL12'], JSON_THROW_ON_ERROR),
                'insight_text' => 'AL12 second insight',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $controller = Mockery::mock(InsightController::class);
        $controller->shouldReceive('warmSectorCache')->once()->with('AL12');
        $controller->shouldReceive('warmSectorCache')->once()->with('B1');
        $this->app->instance(InsightController::class, $controller);

        $this->artisan('insights:warm')
            ->expectsOutput('Warming AL12')
            ->expectsOutput('Warming B1')
            ->expectsOutput('Insight cache warming complete (2 sectors)')
            ->assertExitCode(0);
    }
}
