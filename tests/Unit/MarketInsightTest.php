<?php

namespace Tests\Unit;

use App\Models\MarketInsight;
use PHPUnit\Framework\TestCase;

class MarketInsightTest extends TestCase
{
    public function test_market_insight_uses_expected_table_fillable_fields_and_casts(): void
    {
        $model = new MarketInsight;

        $this->assertSame('market_insights', $model->getTable());
        $this->assertSame([
            'area_type',
            'area_code',
            'insight_type',
            'metric_value',
            'transactions',
            'period_start',
            'period_end',
            'supporting_data',
            'insight_text',
        ], $model->getFillable());
        $this->assertSame('array', $model->getCasts()['supporting_data']);
    }
}
