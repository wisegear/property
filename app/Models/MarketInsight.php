<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketInsight extends Model
{
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
