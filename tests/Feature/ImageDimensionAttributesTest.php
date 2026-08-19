<?php

namespace Tests\Feature;

use Tests\TestCase;

class ImageDimensionAttributesTest extends TestCase
{
    public function test_home_page_hero_renders_the_app_promotion_with_intrinsic_dimensions(): void
    {
        $renderedHome = view('pages.home', [
            'posts' => collect(),
            'stats' => [],
            'totalStress' => null,
            'homepageMarketMovements' => [],
            'homepageSwapRates' => ['latestAvailableDate' => null, 'rates' => []],
            'liveSignalsCount' => 0,
            'marketInsightsCount' => 0,
            'signalTypesCount' => 0,
            'marketInsightSignalCount' => 0,
            'topSignal' => [],
        ])->render();

        $this->assertStringContainsString(
            'src="'.asset('/propertyresearch-app.png').'"',
            $renderedHome
        );
        $this->assertStringContainsString('width="1536"', $renderedHome);
        $this->assertStringContainsString('height="1024"', $renderedHome);
    }

    public function test_login_page_logo_includes_intrinsic_dimensions(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee(
            'src="'.asset('assets/images/site/research-logo-4.png').'"',
            false
        );
        $response->assertSee('width="512"', false);
        $response->assertSee('height="512"', false);
    }
}
