<?php

namespace Tests\Unit;

use App\Models\MarketInsight;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class InsightsIndexViewTest extends TestCase
{
    public function test_index_cards_allow_full_explanation_text_to_render(): void
    {
        $view = $this->view('insights.index', [
            'query' => new LengthAwarePaginator([
                MarketInsight::make([
                    'area_code' => 'Manchester',
                    'insight_type' => 'liquidity_stress',
                    'insight_text' => 'Prices increased 21% year-on-year based on 142 sales and the explanation should remain fully visible inside the card without being truncated.',
                    'transactions' => 142,
                    'period_start' => Carbon::create(2025, 1, 1),
                    'period_end' => Carbon::create(2025, 12, 31),
                ]),
            ], 1, 20),
            'insightTypes' => [
                'price_spike' => 'Price Spike',
                'price_collapse' => 'Price Collapse',
                'demand_collapse' => 'Demand Collapse',
                'liquidity_stress' => 'Liquidity Stress',
                'liquidity_surge' => 'Liquidity Surge',
                'market_freeze' => 'Market Freeze',
                'sector_outperformance' => 'Sector Outperformance',
                'momentum_reversal' => 'Momentum Reversal',
                'unexpected_hotspot' => 'Unexpected Hotspot',
            ],
            'insightDescriptions' => [
                'price_spike' => 'Median prices have risen unusually fast over the latest rolling 12-month period, which may indicate intense local demand or constrained supply.',
                'price_collapse' => 'Median prices have fallen sharply over the latest rolling 12-month period, which can point to weakening demand, repricing, or distressed local conditions.',
                'demand_collapse' => 'Transaction volumes have dropped hard compared with the prior year, suggesting buyers have pulled back or activity has stalled.',
                'liquidity_stress' => 'Transaction volumes have fallen sharply while prices continue rising, suggesting weakening market liquidity.',
                'liquidity_surge' => 'Transaction volumes have risen strongly compared with the prior year, showing a sudden increase in market activity.',
                'market_freeze' => 'Transaction volumes have fallen so far that the market may be freezing up, with far fewer homes successfully completing sales.',
                'sector_outperformance' => 'This postcode sector is outperforming the wider national market, with stronger local price growth than the UK benchmark.',
                'momentum_reversal' => 'Earlier strong price growth has turned into decline, which can signal that local market momentum is rolling over.',
                'unexpected_hotspot' => 'This postcode sector is rising much faster than the national average, suggesting unusually strong local demand or catch-up growth.',
            ],
            'insightTypeCounts' => [
                'price_spike' => 10,
                'price_collapse' => 32,
                'demand_collapse' => 7,
                'liquidity_stress' => 4,
                'liquidity_surge' => 2,
                'market_freeze' => 1,
                'sector_outperformance' => 6,
                'momentum_reversal' => 8,
                'unexpected_hotspot' => 3,
            ],
            'lastRunAt' => Carbon::create(2026, 3, 13, 14, 45),
            'selectedType' => 'liquidity_stress',
            'search' => '',
            'sort' => 'sector_asc',
        ]);

        $rendered = (string) $view;

        $this->assertStringContainsString('min-h-[240px]', $rendered);
        $this->assertStringContainsString('flex h-full min-h-[240px] flex-col', $rendered);
        $this->assertStringContainsString('flex flex-1 flex-col gap-4', $rendered);
        $this->assertStringContainsString('class="mt-auto"', $rendered);
        $this->assertStringContainsString('text-sm leading-6 text-zinc-700', $rendered);
        $this->assertStringContainsString('Liquidity Stress', $rendered);
        $this->assertStringContainsString('Transaction volumes have fallen sharply while prices continue rising, suggesting weakening market liquidity.', $rendered);
        $this->assertStringContainsString('Last run 13 Mar 2026, 14:45', $rendered);
        $this->assertStringContainsString('Find a place or signal', $rendered);
        $this->assertStringContainsString('Filter insights', $rendered);
        $this->assertStringContainsString('Search by area code or insight text.', $rendered);
        $this->assertStringContainsString('Jump straight to a specific anomaly type.', $rendered);
        $this->assertStringContainsString('What the nine insights mean', $rendered);
        $this->assertStringContainsString('Open this panel for a plain-language explanation of each market signal.', $rendered);
        $this->assertStringContainsString('Median prices have risen unusually fast over the latest rolling 12-month period, which may indicate intense local demand or constrained supply.', $rendered);
        $this->assertStringContainsString('This postcode sector is outperforming the wider national market, with stronger local price growth than the UK benchmark.', $rendered);
        $this->assertStringContainsString('All Insights (1)', $rendered);
        $this->assertStringContainsString('Price Spike (10)', $rendered);
        $this->assertStringContainsString('Price Collapse (32)', $rendered);
        $this->assertStringContainsString('Liquidity Stress (4)', $rendered);
        $this->assertStringNotContainsString('line-clamp-2', $rendered);
        $view->assertSee('Prices increased 21% year-on-year based on 142 sales and the explanation should remain fully visible inside the card without being truncated.');
    }
}
