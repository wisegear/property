<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UnemploymentMonthly extends Model
{
    protected $table = 'unemployment_monthly';

    protected $fillable = [
        'date',
        'single_month',
        'single',
        'three_month',
    ];

    protected $casts = [
        'date' => 'date',
        'single_month' => 'integer',
        'single' => 'float',
        'three_month' => 'float',
    ];
}
