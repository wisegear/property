<?php

return [
    'enabled' => env('ANALYTICS_ENABLED', true),

    'cookie_name' => env('ANALYTICS_COOKIE_NAME', 'pr_avid'),

    'bot_user_agent_patterns' => [
        'Googlebot',
        'Bingbot',
        'AhrefsBot',
        'SemrushBot',
        'MJ12bot',
        'DotBot',
        'facebookexternalhit',
        'meta-externalagent',
        'Twitterbot',
        'LinkedInBot',
        'Slackbot',
        'Discordbot',
    ],

    'skipped_route_prefixes' => [
        'admin',
        'login',
        'logout',
        'register',
        'password',
        'verify-email',
        'email',
        'sponsor',
        'livewire',
        'telescope',
        'horizon',
        'up',
        'api',
        'assets',
        'build',
        '_debugbar',
    ],

    'sponsor_dashboard_cache_ttl' => (int) env('ANALYTICS_SPONSOR_CACHE_TTL', 3600),

    'admin_dashboard_cache_ttl' => (int) env('ANALYTICS_ADMIN_CACHE_TTL', 900),
];
