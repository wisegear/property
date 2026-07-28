<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SwapRatesApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_the_complete_swap_rates_dashboard(): void
    {
        foreach ([2, 5, 10] as $termYears) {
            foreach (range(1, 6) as $day) {
                DB::table('swap_rates')->insert([
                    'curve_type' => 'ois',
                    'term_years' => $termYears,
                    'rate_date' => "2026-07-0{$day}",
                    'rate' => 4 + ($termYears / 100) + ($day / 100),
                    'daily_change' => 0.01,
                    'source' => 'Bank of England',
                ]);
            }
        }

        DB::table('interest_rates')->insert([
            'effective_date' => '2026-01-01',
            'rate' => 4.25,
        ]);

        $response = $this->getJson(route('api.v1.insights.swap-rates', absolute: false));

        $response
            ->assertOk()
            ->assertJsonStructure(['data' => [
                'title',
                'description',
                'source_note',
                'latest_available_date',
                'mortgage_market_summary',
                'latest_movement_summary',
                'rates' => ['*' => [
                    'term_years',
                    'label',
                    'latest_rate',
                    'latest_rate_date',
                    'previous_rate',
                    'previous_rate_date',
                    'latest_movement',
                    'five_day_change',
                    'trend',
                    'sparkline',
                    'sparkline_dates',
                    'range_52_week' => ['low', 'high'],
                ]],
                'rate_chart' => ['labels', 'datasets'],
                'bank_rate_comparison_chart' => ['labels', 'datasets'],
                'current_rates',
                'mortgage_context',
                'understanding_swaps',
                'faq',
                'update_note',
                'website_url',
            ]])
            ->assertJsonCount(3, 'data.rates')
            ->assertJsonCount(4, 'data.faq')
            ->assertJsonPath('data.latest_available_date', '2026-07-06')
            ->assertJsonPath('data.rates.0.term_years', 2)
            ->assertJsonPath('data.rates.0.latest_rate', 4.08)
            ->assertJsonPath('data.rates.0.five_day_change', 0.04)
            ->assertJsonPath('data.website_url', route('insights.swap-rates'));
    }

    public function test_it_returns_an_empty_dashboard_when_no_rates_are_available(): void
    {
        $this->getJson(route('api.v1.insights.swap-rates', absolute: false))
            ->assertOk()
            ->assertJsonPath('data.latest_available_date', null)
            ->assertJsonCount(3, 'data.rates')
            ->assertJsonPath('data.rates.0.latest_rate', null)
            ->assertJsonCount(3, 'data.current_rates')
            ->assertJsonPath('data.current_rates.0.rate', null)
            ->assertJsonCount(4, 'data.faq');
    }
}
