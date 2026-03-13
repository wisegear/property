<?php

namespace Tests\Unit;

use App\Services\InsightWriter;
use PHPUnit\Framework\TestCase;

class InsightWriterTest extends TestCase
{
    public function test_price_spike_writes_a_short_factual_sentence(): void
    {
        $writer = new InsightWriter;

        $result = $writer->priceSpike([
            'postcode' => 'NW8',
            'growth' => '12.4',
            'sales' => 42,
            'period_label' => '01 Feb 2025 to 31 Jan 2026',
        ]);

        $this->assertSame(
            'Median property prices in NW8 rose 12.4% in 01 Feb 2025 to 31 Jan 2026 based on 42 recorded sales.',
            $result
        );
    }

    public function test_demand_collapse_writes_a_short_factual_sentence(): void
    {
        $writer = new InsightWriter;

        $result = $writer->demandCollapse((object) [
            'area_code' => 'M1',
            'sales_change' => '18.7',
            'sales' => 15,
            'period_label' => '01 Feb 2025 to 31 Jan 2026',
        ]);

        $this->assertSame(
            'Property transactions in M1 fell 18.7% in 01 Feb 2025 to 31 Jan 2026 based on 15 recorded sales.',
            $result
        );
    }

    public function test_price_collapse_writes_the_supporting_price_comparison(): void
    {
        $writer = new InsightWriter;

        $result = $writer->priceCollapse([
            'area_code' => 'AL12',
            'growth' => '16.4',
            'previous_price' => '250,000',
            'current_price' => '209,000',
        ]);

        $this->assertSame(
            'Median property prices in postcode sector AL12 fell 16.4% over the last 12 months. Previous period median price: £250,000. Current period median price: £209,000.',
            $result
        );
    }

    public function test_liquidity_surge_writes_a_short_factual_sentence(): void
    {
        $writer = new InsightWriter;

        $result = $writer->liquiditySurge([
            'area_code' => 'B1',
            'sales_change' => '42.0',
        ]);

        $this->assertSame(
            'Property transactions in postcode sector B1 increased 42.0% over the past 12 months compared with the previous year.',
            $result
        );
    }

    public function test_liquidity_stress_writes_a_short_factual_sentence(): void
    {
        $writer = new InsightWriter;

        $result = $writer->liquidityStress([
            'area_code' => 'B1',
            'sales_change' => '44.0',
            'price_growth' => '6.5',
            'period_label' => '01 Feb 2025 to 31 Jan 2026',
        ]);

        $this->assertSame(
            'Property transactions in postcode sector B1 fell 44.0% in 01 Feb 2025 to 31 Jan 2026 while median prices still rose 6.5%, suggesting weakening market liquidity.',
            $result
        );
    }

    public function test_market_freeze_writes_a_short_factual_sentence(): void
    {
        $writer = new InsightWriter;

        $result = $writer->marketFreeze([
            'area_code' => 'B1',
            'sales_change' => '63.5',
        ]);

        $this->assertSame(
            'Property transactions in postcode sector B1 fell 63.5% over the past 12 months, indicating a sharp slowdown in market activity.',
            $result
        );
    }

    public function test_sector_outperformance_writes_a_short_factual_sentence(): void
    {
        $writer = new InsightWriter;

        $result = $writer->sectorOutperformance([
            'area_code' => 'SW1A',
            'sector_growth' => '14.2',
            'uk_growth' => '2.8',
            'sales' => 27,
            'period_label' => '01 Feb 2025 to 31 Jan 2026',
        ]);

        $this->assertSame(
            'Median property prices in SW1A rose 14.2% in 01 Feb 2025 to 31 Jan 2026 versus 2.8% nationally based on 27 recorded sales.',
            $result
        );
    }

    public function test_unexpected_hotspot_writes_a_short_factual_sentence(): void
    {
        $writer = new InsightWriter;

        $result = $writer->unexpectedHotspot([
            'area_code' => 'AL12',
            'sector_growth' => '18.6',
            'uk_growth' => '7.2',
        ]);

        $this->assertSame(
            "Median property prices in postcode sector AL12 rose 18.6% over the past 12 months, significantly outperforming the UK average increase of 7.2%. Despite this surge, the sector's median price remains below the national average.",
            $result
        );
    }
}
