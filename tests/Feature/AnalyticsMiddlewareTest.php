<?php

namespace Tests\Feature;

use App\Models\AnalyticsEvent;
use App\Models\AnalyticsPageView;
use App\Models\AnalyticsVisit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_normal_get_page_request_sets_cookie_and_records_visit_and_page_view(): void
    {
        $response = $this->get('/property/search');

        $response->assertOk();
        $response->assertCookie('pr_avid');

        $visit = AnalyticsVisit::query()->first();
        $pageView = AnalyticsPageView::query()->first();

        $this->assertNotNull($visit);
        $this->assertNotNull($pageView);
        $this->assertSame('property.search', $pageView->route_name);
        $this->assertSame('property', $pageView->page_type);
        $this->assertSame($visit->anon_visit_id, $pageView->anon_visit_id);
    }

    public function test_bot_requests_are_marked_and_do_not_create_page_views(): void
    {
        $response = $this->withHeader('User-Agent', 'Mozilla/5.0 Googlebot/2.1')
            ->get('/property/search');

        $response->assertOk();

        $visit = AnalyticsVisit::query()->first();

        $this->assertNotNull($visit);
        $this->assertTrue($visit->is_bot);
        $this->assertSame(0, AnalyticsPageView::query()->count());
    }

    public function test_auth_routes_are_skipped(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $this->assertSame(0, AnalyticsVisit::query()->count());
        $this->assertSame(0, AnalyticsPageView::query()->count());
    }

    public function test_form_events_also_write_normalized_analytics_events(): void
    {
        $response = $this->post('/mortgage-calculator', [
            'amount' => '250,000',
            'term' => 30,
            'rate' => '4.5',
        ]);

        $response->assertOk();

        $event = AnalyticsEvent::query()->first();

        $this->assertNotNull($event);
        $this->assertSame('calculator', $event->event_type);
        $this->assertSame('mortgage_calculator', $event->event_key);
    }
}
