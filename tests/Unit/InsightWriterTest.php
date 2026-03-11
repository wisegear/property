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
        ]);

        $this->assertSame(
            'Average property prices in NW8 increased 12.4% year-on-year based on 42 recorded sales.',
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
        ]);

        $this->assertSame(
            'Property transactions in M1 fell 18.7% compared with the previous year based on 15 recorded transactions.',
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
        ]);

        $this->assertSame(
            'Average property prices in sector SW1A increased 14.2% versus 2.8% nationally based on 27 recorded sales.',
            $result
        );
    }
}
