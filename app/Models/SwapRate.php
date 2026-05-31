<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SwapRate extends Model
{
    protected $fillable = [
        'rate_date',
        'curve_type',
        'term_years',
        'rate',
        'daily_change',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'rate_date' => 'date',
            'term_years' => 'integer',
            'rate' => 'decimal:4',
            'daily_change' => 'decimal:4',
        ];
    }
}
