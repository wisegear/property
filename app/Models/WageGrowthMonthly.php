<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WageGrowthMonthly extends Model
{
    protected $table = 'wage_growth_monthly';

    protected $fillable = [
        'date',
        'three_month_avg_yoy',
    ];

    protected $casts = [
        'date' => 'date',
        'three_month_avg_yoy' => 'float',
    ];
}
