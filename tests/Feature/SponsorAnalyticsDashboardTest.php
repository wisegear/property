<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SponsorAnalyticsDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_sponsor_dashboard_shows_aggregated_bot_filtered_stats_without_ips(): void
    {
        $user = User::factory()->create();

        DB::table('analytics_visits')->insert([
            [
                'anon_visit_id' => 'visit-human-1',
                'ip_address' => '198.51.100.10',
                'country_code' => 'GB',
                'user_agent' => 'Mozilla/5.0',
                'device_type' => 'desktop',
                'browser' => 'Chrome',
                'landing_page' => 'https://prop.test/property/search',
                'is_bot' => false,
                'first_seen_at' => now()->subDays(5),
                'last_seen_at' => now()->subDays(1),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'anon_visit_id' => 'visit-human-2',
                'ip_address' => '203.0.113.20',
                'country_code' => 'US',
                'user_agent' => 'Mozilla/5.0',
                'device_type' => 'mobile',
                'browser' => 'Safari',
                'landing_page' => 'https://prop.test/epc/search',
                'is_bot' => false,
                'first_seen_at' => now()->subDays(3),
                'last_seen_at' => now()->subDays(2),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'anon_visit_id' => 'visit-bot-1',
                'ip_address' => '192.0.2.44',
                'country_code' => 'GB',
                'user_agent' => 'Googlebot',
                'device_type' => 'desktop',
                'browser' => 'Other',
                'landing_page' => 'https://prop.test/property/search',
                'is_bot' => true,
                'first_seen_at' => now()->subDays(2),
                'last_seen_at' => now()->subDay(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('analytics_page_views')->insert([
            [
                'anon_visit_id' => 'visit-human-1',
                'ip_address' => '198.51.100.10',
                'url' => 'https://prop.test/property/search',
                'route_name' => 'property.search',
                'page_type' => 'property',
                'viewed_at' => now()->subDays(1),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'anon_visit_id' => 'visit-human-2',
                'ip_address' => '203.0.113.20',
                'url' => 'https://prop.test/epc/search',
                'route_name' => 'epc.search',
                'page_type' => 'epc',
                'viewed_at' => now()->subDays(2),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'anon_visit_id' => 'visit-bot-1',
                'ip_address' => '192.0.2.44',
                'url' => 'https://prop.test/property/search',
                'route_name' => 'property.search',
                'page_type' => 'property',
                'viewed_at' => now()->subDay(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('analytics_events')->insert([
            [
                'anon_visit_id' => 'visit-human-1',
                'ip_address' => '198.51.100.10',
                'event_type' => 'search',
                'event_key' => 'property_search',
                'payload' => json_encode(['postcode' => 'WR5 3EU']),
                'created_at' => now()->subDays(1),
            ],
            [
                'anon_visit_id' => 'visit-human-1',
                'ip_address' => '198.51.100.10',
                'event_type' => 'calculator',
                'event_key' => 'mortgage_calculator',
                'payload' => json_encode(['amount' => 250000]),
                'created_at' => now()->subDays(1),
            ],
            [
                'anon_visit_id' => 'visit-bot-1',
                'ip_address' => '192.0.2.44',
                'event_type' => 'search',
                'event_key' => 'property_search',
                'payload' => json_encode(['postcode' => 'B1 1AA']),
                'created_at' => now()->subDay(),
            ],
        ]);

        Cache::forget('analytics:sponsor:stats:30');

        $response = $this->actingAs($user)->get(route('sponsor.analytics', absolute: false));

        $response->assertOk();
        $response->assertSee('Audience Summary');
        $response->assertSee('2');
        $response->assertSee('50.0%');
        $response->assertSee('Mortgage calculator uses');
        $response->assertDontSee('198.51.100.10');
        $response->assertDontSee('203.0.113.20');
        $response->assertViewHas('stats', function (array $stats): bool {
            return $stats['windows'][30]['unique_visitors'] === 2
                && $stats['windows'][30]['page_views'] === 2
                && $stats['event_totals']['postcode_property_searches'] === 1
                && $stats['event_totals']['mortgage_calculator_uses'] === 1;
        });
    }
}
