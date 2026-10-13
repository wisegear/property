<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketInsight extends Model
{
    public const SIGNAL_GROUPS = [
        'rising' => ['price_spike', 'sector_outperformance', 'unexpected_hotspot'],
        'falling' => ['price_collapse', 'momentum_reversal'],
        'activity' => ['demand_collapse', 'market_freeze', 'liquidity_stress', 'liquidity_surge'],
    ];

    public function researchLabel(): string
    {
        return match ($this->insight_type) {
            'price_spike', 'sector_outperformance', 'unexpected_hotspot' => 'Prices rising',
            'price_collapse', 'momentum_reversal' => 'Prices falling',
            default => 'Activity changing',
        };
    }

    public function researchFinding(): string
    {
        $change = number_format((float) $this->metric_value, 1);

        return match ($this->insight_type) {
            'price_spike', 'price_collapse' => "Median sale price changed by {$change}%.",
            'sector_outperformance', 'unexpected_hotspot' => "Local median price growth was {$change}%, flagged against the national benchmark.",
            'momentum_reversal' => "Median price growth turned negative: {$change}% in the latest comparison.",
            default => "Recorded sales changed by {$change}% compared with the previous 12 months.",
        };
    }

    protected $table = 'market_insights';

    protected $fillable = [
        'area_type',
        'area_code',
        'insight_type',
        'metric_value',
        'transactions',
        'period_start',
        'period_end',
        'supporting_data',
        'insight_text',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'datetime',
            'period_end' => 'datetime',
            'supporting_data' => 'array',
        ];
    }
}
