<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnalyticsVisit extends Model
{
    protected $fillable = [
        'anon_visit_id',
        'ip_address',
        'country_code',
        'user_agent',
        'device_type',
        'browser',
        'referrer',
        'landing_page',
        'is_bot',
        'first_seen_at',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'is_bot' => 'boolean',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }
}
