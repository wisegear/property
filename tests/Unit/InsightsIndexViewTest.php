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
                    'insight_type' => 'price_spike',
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
                'liquidity_surge' => 'Liquidity Surge',
                'market_freeze' => 'Market Freeze',
                'sector_outperformance' => 'Sector Outperformance',
                'momentum_reversal' => 'Momentum Reversal',
                'unexpected_hotspot' => 'Unexpected Hotspot',
            ],
            'selectedType' => '',
            'search' => '',
            'sort' => 'sector_asc',
        ]);

        $rendered = (string) $view;

        $this->assertStringContainsString('min-h-[240px]', $rendered);
        $this->assertStringContainsString('flex h-full min-h-[240px] flex-col', $rendered);
        $this->assertStringContainsString('flex flex-1 flex-col gap-4', $rendered);
        $this->assertStringContainsString('class="mt-auto"', $rendered);
        $this->assertStringContainsString('text-sm leading-6 text-zinc-700', $rendered);
        $this->assertStringNotContainsString('line-clamp-2', $rendered);
        $view->assertSee('Prices increased 21% year-on-year based on 142 sales and the explanation should remain fully visible inside the card without being truncated.');
    }
}
