<?php

return [
    'enabled' => env('ANALYTICS_ENABLED', true),

    'cookie_name' => env('ANALYTICS_COOKIE_NAME', 'pr_avid'),

    'bot_user_agents' => [
        ['name' => 'Googlebot', 'pattern' => 'Googlebot'],
        ['name' => 'AdsBot-Google', 'pattern' => 'AdsBot-Google'],
        ['name' => 'GoogleOther', 'pattern' => 'GoogleOther'],
        ['name' => 'APIs-Google', 'pattern' => 'APIs-Google'],
        ['name' => 'Mediapartners-Google', 'pattern' => 'Mediapartners-Google'],
        ['name' => 'bingbot', 'pattern' => 'bingbot'],
        ['name' => 'BingPreview', 'pattern' => 'BingPreview'],
        ['name' => 'facebookexternalhit', 'pattern' => 'facebookexternalhit'],
        ['name' => 'meta-externalagent', 'pattern' => 'meta-externalagent'],
        ['name' => 'Twitterbot', 'pattern' => 'Twitterbot'],
        ['name' => 'LinkedInBot', 'pattern' => 'LinkedInBot'],
        ['name' => 'Slackbot', 'pattern' => 'Slackbot'],
        ['name' => 'Discordbot', 'pattern' => 'Discordbot'],
        ['name' => 'AhrefsBot', 'pattern' => 'AhrefsBot'],
        ['name' => 'SemrushBot', 'pattern' => 'SemrushBot'],
        ['name' => 'MJ12bot', 'pattern' => 'MJ12bot'],
        ['name' => 'DotBot', 'pattern' => 'DotBot'],
        ['name' => 'YandexBot', 'pattern' => 'YandexBot'],
        ['name' => 'PetalBot', 'pattern' => 'PetalBot'],
        ['name' => 'Bytespider', 'pattern' => 'Bytespider'],
        ['name' => 'GPTBot', 'pattern' => 'GPTBot'],
        ['name' => 'ClaudeBot', 'pattern' => 'ClaudeBot'],
        ['name' => 'CCBot', 'pattern' => 'CCBot'],
        ['name' => 'Applebot', 'pattern' => 'Applebot'],
        ['name' => 'DuckDuckBot', 'pattern' => 'DuckDuckBot'],
    ],

    'bot_ip_ranges' => [
        ['name' => 'Google', 'cidr' => '66.249.0.0/16'],
        ['name' => 'Microsoft/Bing', 'cidr' => '40.77.0.0/16'],
        ['name' => 'Microsoft/Bing', 'cidr' => '157.55.0.0/16'],
        ['name' => 'Microsoft/Azure', 'cidr' => '20.0.0.0/8'],
        ['name' => 'Meta/Facebook', 'cidr' => '2a03:2880::/32'],
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
