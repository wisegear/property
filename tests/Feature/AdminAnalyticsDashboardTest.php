<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminAnalyticsDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_exposes_internal_analytics_sections(): void
    {
        $admin = $this->createAdminUser();

        DB::table('analytics_visits')->insert([
            [
                'anon_visit_id' => 'visit-1',
                'ip_address' => '198.51.100.10',
                'country_code' => 'GB',
                'user_agent' => 'Mozilla/5.0',
                'device_type' => 'desktop',
                'browser' => 'Chrome',
                'landing_page' => 'https://prop.test/property/search',
                'is_bot' => false,
                'first_seen_at' => now()->subDays(2),
                'last_seen_at' => now()->subDay(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'anon_visit_id' => 'visit-2',
                'ip_address' => '198.51.100.10',
                'country_code' => 'GB',
                'user_agent' => 'Mozilla/5.0',
                'device_type' => 'desktop',
                'browser' => 'Chrome',
                'landing_page' => 'https://prop.test/epc/search',
                'is_bot' => false,
                'first_seen_at' => now()->subDays(6),
                'last_seen_at' => now()->subDays(2),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'anon_visit_id' => 'visit-bot',
                'ip_address' => '192.0.2.44',
                'country_code' => 'GB',
                'user_agent' => 'Googlebot',
                'device_type' => 'desktop',
                'browser' => 'Other',
                'landing_page' => 'https://prop.test/property/search',
                'is_bot' => true,
                'first_seen_at' => now()->subDay(),
                'last_seen_at' => now()->subDay(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('analytics_page_views')->insert([
            [
                'anon_visit_id' => 'visit-1',
                'ip_address' => '198.51.100.10',
                'url' => 'https://prop.test/property/search',
                'route_name' => 'property.search',
                'page_type' => 'property',
                'viewed_at' => now()->subDay(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'anon_visit_id' => 'visit-2',
                'ip_address' => '198.51.100.10',
                'url' => 'https://prop.test/epc/search',
                'route_name' => 'epc.search',
                'page_type' => 'epc',
                'viewed_at' => now()->subDays(2),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'anon_visit_id' => 'visit-bot',
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
                'anon_visit_id' => 'visit-1',
                'ip_address' => '198.51.100.10',
                'event_type' => 'search',
                'event_key' => 'property_search',
                'payload' => json_encode(['postcode' => 'WR5 3EU']),
                'created_at' => now()->subDay(),
            ],
        ]);

        Cache::forget('analytics:admin:stats:30');

        $response = $this->actingAs($admin)->get('/admin');

        $response->assertOk();
        $response->assertSee('Internal Analytics');
        $response->assertSee('198.51.100.10');
        $response->assertSee('property_search');
        $response->assertViewHas('admin_analytics', function (array $stats): bool {
            return $stats['periods'][7]['visitors'] === 3
                && $stats['periods'][7]['bot_visits'] === 1
                && count($stats['top_ip_addresses']) >= 1;
        });
    }

    private function createAdminUser(): User
    {
        $admin = User::factory()->create();

        $roleId = DB::table('user_roles')->insertGetId([
            'name' => 'Admin',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('user_roles_pivot')->insert([
            'role_id' => $roleId,
            'user_id' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $admin;
    }
}
