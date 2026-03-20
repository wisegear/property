<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Minimum sector transactions
    |--------------------------------------------------------------------------
    |
    | Minimum number of transactions required for a postcode sector to
    | generate an insight signal.
    |
    */

    'min_sector_transactions' => 20,
    'min_period_transactions' => 10,

    'strong_thresholds' => [
        'price_spike' => 40,
        'price_collapse' => -30,
        'demand_collapse' => -40,
        'liquidity_stress' => -40,
        'liquidity_surge' => 40,
        'market_freeze' => -50,
        'sector_outperformance' => 30,
        'momentum_reversal' => 35,
        'unexpected_hotspot' => 50,
    ],

    'max_insights_total' => 100,
    'max_per_type' => 40,
];
