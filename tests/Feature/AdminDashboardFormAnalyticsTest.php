<?php

namespace Tests\Feature;

use App\Models\FormEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminDashboardFormAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_includes_form_event_metrics_grouped_by_form_key(): void
    {
        $admin = $this->createAdminUser();

        FormEvent::query()->create([
            'form_key' => 'property_search',
            'anon_visit_id' => 'visit-1',
            'payload' => ['postcode' => 'WR5 3EU'],
            'created_at' => now()->subHours(2),
        ]);

        FormEvent::query()->create([
            'form_key' => 'property_search',
            'anon_visit_id' => 'visit-1',
            'payload' => ['postcode' => 'WR5 3EU'],
            'created_at' => now()->subHours(1),
        ]);

        FormEvent::query()->create([
            'form_key' => 'property_search',
            'anon_visit_id' => 'visit-2',
            'payload' => ['postcode' => 'B1 1AA'],
            'created_at' => now()->subHours(30),
        ]);

        FormEvent::query()->create([
            'form_key' => 'epc_england_wales',
            'anon_visit_id' => 'visit-3',
            'payload' => ['postcode' => 'L1 8JQ'],
            'created_at' => now()->subHours(40),
        ]);

        FormEvent::query()->create([
            'form_key' => '/epc/postcode/',
            'anon_visit_id' => 'visit-4',
            'payload' => ['postcode' => 'SW7 5PH'],
            'created_at' => now()->subHours(3),
        ]);

        FormEvent::query()->create([
            'form_key' => '/epc/scotland/postcode/',
            'anon_visit_id' => 'visit-5',
            'payload' => ['postcode' => 'FK7 8YY'],
            'created_at' => now()->subHours(5),
        ]);

        $response = $this->actingAs($admin)->get('/admin');

        $response->assertOk();
        $response->assertViewHas('form_event_metrics', function ($metrics) {
            $metricsByFormKey = $metrics->keyBy('form_key');

            return $metricsByFormKey->count() === 4
                && (int) $metricsByFormKey->get('property_search')->total_events === 3
                && (int) $metricsByFormKey->get('property_search')->events_last_24h === 2
                && (int) $metricsByFormKey->get('property_search')->unique_visits === 2
                && (int) $metricsByFormKey->get('epc_england_wales')->total_events === 1
                && (int) $metricsByFormKey->get('epc_england_wales')->events_last_24h === 0
                && (int) $metricsByFormKey->get('epc_england_wales')->unique_visits === 1
                && (int) $metricsByFormKey->get('/epc/postcode/')->total_events === 1
                && (int) $metricsByFormKey->get('/epc/postcode/')->events_last_24h === 1
                && (int) $metricsByFormKey->get('/epc/postcode/')->unique_visits === 1
                && (int) $metricsByFormKey->get('/epc/scotland/postcode/')->total_events === 1
                && (int) $metricsByFormKey->get('/epc/scotland/postcode/')->events_last_24h === 1
                && (int) $metricsByFormKey->get('/epc/scotland/postcode/')->unique_visits === 1;
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
