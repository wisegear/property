<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class FormAnalyticsTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_property_search_records_form_event_after_validation(): void
    {
        $response = $this->get('/property/search?postcode=WR5+3EU');

        $response->assertOk();
        $event = DB::table('form_events')->where('form_key', 'property_search')->first();

        $this->assertNotNull($event);
        $this->assertSame('WR5 3EU', $this->decodePayload($event->payload)['postcode'] ?? null);
    }

    public function test_tracked_form_sets_cookie_when_missing(): void
    {
        $response = $this->get('/property/search?postcode=WR5+3EU');

        $response->assertOk();
        $response->assertCookie('pr_avid');
    }

    public function test_area_search_page_sets_cookie_when_missing(): void
    {
        $response = $this->get('/property/search');

        $response->assertOk();
        $response->assertCookie('pr_avid');
    }

    public function test_two_submissions_with_same_cookie_share_anon_visit_id(): void
    {
        $anonVisitId = (string) Str::uuid();

        $firstResponse = $this->withCookie('pr_avid', $anonVisitId)
            ->get('/property/search?postcode=WR5+3EU');

        $firstResponse->assertOk();

        $secondResponse = $this->withCookie('pr_avid', $anonVisitId)
            ->get('/epc/search?postcode=WR5+3EU');

        $secondResponse->assertOk();

        $propertyEvent = DB::table('form_events')->where('form_key', 'property_search')->first();
        $epcEvent = DB::table('form_events')->where('form_key', 'epc_england_wales')->first();

        $this->assertNotNull($propertyEvent);
        $this->assertNotNull($epcEvent);
        $this->assertSame($anonVisitId, $propertyEvent->anon_visit_id);
        $this->assertSame($anonVisitId, $epcEvent->anon_visit_id);
    }

    public function test_epc_search_records_form_event_after_validation(): void
    {
        $response = $this->get('/epc/search?postcode=WR5+3EU');

        $response->assertOk();
        $event = DB::table('form_events')->where('form_key', 'epc_england_wales')->first();

        $this->assertNotNull($event);
        $this->assertSame('WR5 3EU', $this->decodePayload($event->payload)['postcode'] ?? null);
    }

    public function test_epc_scotland_search_records_form_event_after_validation(): void
    {
        $response = $this->get('/epc/search_scotland?postcode=EH1+1AA');

        $response->assertOk();
        $event = DB::table('form_events')->where('form_key', 'epc_scotland')->first();

        $this->assertNotNull($event);
        $this->assertSame('EH1 1AA', $this->decodePayload($event->payload)['postcode'] ?? null);
    }

    public function test_stamp_duty_calculation_records_analytics_event(): void
    {
        $response = $this->postJson('/stamp-duty/calc', [
            'price' => 375000,
            'region' => 'eng-ni',
            'buyer_type' => 'main',
            'additional_property' => false,
            'non_resident' => false,
        ]);

        $response->assertOk();
        $event = DB::table('form_events')->where('form_key', 'stamp_duty')->first();

        $this->assertNotNull($event);
        $payload = $this->decodePayload($event->payload);
        $this->assertEquals(375000, $payload['price'] ?? null);
        $this->assertSame('main', $payload['buyer_type'] ?? null);
        $this->assertSame('eng-ni', $payload['region'] ?? null);
    }

    public function test_mortgage_calculator_records_signal_payload(): void
    {
        $response = $this->post('/mortgage-calculator', [
            'amount' => '250,000',
            'term' => 30,
            'rate' => '4.5',
        ]);

        $response->assertOk();
        $event = DB::table('form_events')->where('form_key', 'mortgage_calculator')->first();

        $this->assertNotNull($event);
        $payload = $this->decodePayload($event->payload);
        $this->assertSame(250000, $payload['amount'] ?? null);
        $this->assertSame(30, $payload['term_years'] ?? null);
        $this->assertSame(4.5, $payload['interest_rate'] ?? null);
    }

    public function test_deprivation_postcode_lookup_records_only_on_successful_resolution(): void
    {
        DB::table('onspd')->insert([
            'pcd' => 'WR53EU',
            'pcd2' => 'WR5 3EU',
            'pcds' => 'WR5 3EU',
            'dointr' => '202401',
            'doterm' => null,
            'ctry' => 'E92000001',
            'lsoa21' => 'E01000001',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->get('/deprivation?postcode=WR53EU');

        $response->assertRedirect(route('deprivation.show', ['lsoa21cd' => 'E01000001'], absolute: false));
        $event = DB::table('form_events')->where('form_key', 'deprivation_lookup')->first();

        $this->assertNotNull($event);
        $payload = $this->decodePayload($event->payload);
        $this->assertSame('WR5 3EU', $payload['postcode'] ?? null);
        $this->assertSame('imd_2025', $payload['index_version'] ?? null);
    }

    public function test_failed_validation_does_not_record_form_event(): void
    {
        $response = $this->post('/mortgage-calculator', [
            'amount' => '',
            'term' => 30,
            'rate' => '4.5',
        ]);

        $response->assertSessionHasErrors(['amount']);
        $this->assertSame(0, DB::table('form_events')->count());
    }

    public function test_property_area_selection_records_form_event(): void
    {
        $areas = json_decode(file_get_contents(public_path('data/property_districts.json')), true) ?? [];
        $selected = collect($areas)->first(function ($item) {
            return is_array($item)
                && in_array(($item['type'] ?? ''), ['locality', 'town', 'district', 'county'], true)
                && ! empty($item['name'] ?? $item['label'] ?? null);
        });

        if (! is_array($selected)) {
            $this->markTestSkipped('No area entry available in property_districts.json.');
        }

        $type = (string) $selected['type'];
        $name = (string) ($selected['name'] ?? $selected['label']);
        $slug = Str::slug($name);

        $response = $this->get(route('property.area.show', ['type' => $type, 'slug' => $slug], absolute: false));

        $response->assertOk();
        $response->assertCookie('pr_avid');
        $event = DB::table('form_events')->where('form_key', 'property_area_search')->first();
        $this->assertNotNull($event);

        $payload = $this->decodePayload($event->payload);
        $this->assertSame($type, $payload['area_type'] ?? null);
        $this->assertSame($name, $payload['area_name'] ?? null);
    }

    public function test_form_submission_uses_existing_anon_visit_cookie(): void
    {
        $anonVisitId = (string) Str::uuid();

        $response = $this->withCookie('pr_avid', $anonVisitId)
            ->post('/mortgage-calculator', [
                'amount' => '250,000',
                'term' => 30,
                'rate' => '4.5',
            ]);

        $response->assertOk();
        $event = DB::table('form_events')->where('form_key', 'mortgage_calculator')->first();

        $this->assertNotNull($event);
        $this->assertSame($anonVisitId, $event->anon_visit_id);
    }

    public function test_deprivation_northern_ireland_area_selection_records_form_event(): void
    {
        DB::table('ni_deprivation')->insert([
            'SA2011' => 'N00000001',
            'SOA2001name' => 'Belfast Area',
            'MDM_rank' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->get(route('deprivation.ni.show', ['sa' => 'N00000001'], absolute: false));

        $response->assertOk();
        $event = DB::table('form_events')
            ->where('form_key', 'deprivation_lookup')
            ->where('payload', 'like', '%nimdm_2017%')
            ->first();

        $this->assertNotNull($event);
        $payload = $this->decodePayload($event->payload);
        $this->assertSame('N00000001', $payload['area_code'] ?? null);
        $this->assertSame('nimdm_2017', $payload['index_version'] ?? null);
    }

    public function test_no_ip_user_agent_or_session_id_is_stored(): void
    {
        $response = $this->withHeader('User-Agent', 'Test-Agent/1.0')
            ->get('/property/search?postcode=WR5+3EU');

        $response->assertOk();

        $this->assertFalse(Schema::hasColumn('form_events', 'ip'));
        $this->assertFalse(Schema::hasColumn('form_events', 'ip_address'));
        $this->assertFalse(Schema::hasColumn('form_events', 'user_agent'));
        $this->assertFalse(Schema::hasColumn('form_events', 'session_id'));

        $event = DB::table('form_events')->where('form_key', 'property_search')->first();
        $this->assertNotNull($event);
        $payload = $this->decodePayload($event->payload);
        $this->assertArrayNotHasKey('ip', $payload);
        $this->assertArrayNotHasKey('ip_address', $payload);
        $this->assertArrayNotHasKey('user_agent', $payload);
        $this->assertArrayNotHasKey('session_id', $payload);
    }

    private function decodePayload(mixed $payload): array
    {
        if (is_array($payload)) {
            return $payload;
        }

        if (! is_string($payload) || $payload === '') {
            return [];
        }

        $decoded = json_decode($payload, true);

        return is_array($decoded) ? $decoded : [];
    }
}
