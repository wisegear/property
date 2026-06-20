<?php

namespace Tests\Feature;

use App\Models\AnalyticsVisit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsClassifyBotsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_backfills_bot_status_and_name_from_user_agent_and_ip_ranges(): void
    {
        AnalyticsVisit::query()->create([
            'anon_visit_id' => 'visit-googlebot',
            'ip_address' => '198.51.100.20',
            'country_code' => 'GB',
            'user_agent' => 'Mozilla/5.0 Googlebot/2.1',
            'device_type' => 'desktop',
            'browser' => 'Other',
            'landing_page' => 'https://prop.test/property/search',
            'is_bot' => false,
            'bot_name' => null,
            'first_seen_at' => now()->subDay(),
            'last_seen_at' => now()->subDay(),
        ]);

        AnalyticsVisit::query()->create([
            'anon_visit_id' => 'visit-google-ip',
            'ip_address' => '66.249.64.10',
            'country_code' => 'GB',
            'user_agent' => 'Mozilla/5.0',
            'device_type' => 'desktop',
            'browser' => 'Chrome',
            'landing_page' => 'https://prop.test/epc/search',
            'is_bot' => false,
            'bot_name' => null,
            'first_seen_at' => now()->subDay(),
            'last_seen_at' => now()->subDay(),
        ]);

        AnalyticsVisit::query()->create([
            'anon_visit_id' => 'visit-human',
            'ip_address' => '198.51.100.30',
            'country_code' => 'US',
            'user_agent' => 'Mozilla/5.0',
            'device_type' => 'mobile',
            'browser' => 'Safari',
            'landing_page' => 'https://prop.test/mortgage-calculator',
            'is_bot' => true,
            'bot_name' => 'Old bot',
            'first_seen_at' => now()->subDay(),
            'last_seen_at' => now()->subDay(),
        ]);

        $this->artisan('analytics:classify-bots')
            ->expectsOutput('Classifying analytics visits...')
            ->expectsOutput('Scanned: 3')
            ->expectsOutput('Updated: 3')
            ->expectsOutput('Bot visits: 2')
            ->expectsOutput('Human visits: 1')
            ->assertSuccessful();

        $this->assertTrue(AnalyticsVisit::query()->where('anon_visit_id', 'visit-googlebot')->value('is_bot'));
        $this->assertSame('Googlebot', AnalyticsVisit::query()->where('anon_visit_id', 'visit-googlebot')->value('bot_name'));
        $this->assertTrue(AnalyticsVisit::query()->where('anon_visit_id', 'visit-google-ip')->value('is_bot'));
        $this->assertSame('Google', AnalyticsVisit::query()->where('anon_visit_id', 'visit-google-ip')->value('bot_name'));
        $this->assertFalse(AnalyticsVisit::query()->where('anon_visit_id', 'visit-human')->value('is_bot'));
        $this->assertNull(AnalyticsVisit::query()->where('anon_visit_id', 'visit-human')->value('bot_name'));
    }
}
