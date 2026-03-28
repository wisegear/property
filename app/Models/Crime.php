<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Crime extends Model
{
    protected $table = 'crime';

    public $timestamps = false;

    protected $fillable = [
        'crime_id',
        'month',
        'reported_by',
        'falls_within',
        'longitude',
        'latitude',
        'location',
        'lsoa_code',
        'lsoa_name',
        'crime_type',
        'last_outcome_category',
        'context',
    ];

    protected function casts(): array
    {
        return [
            'month' => 'date',
            'longitude' => 'decimal:7',
            'latitude' => 'decimal:7',
        ];
    }
}
