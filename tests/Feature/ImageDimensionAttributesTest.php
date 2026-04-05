<?php

namespace Tests\Feature;

use Tests\TestCase;

class ImageDimensionAttributesTest extends TestCase
{
    public function test_home_page_images_include_intrinsic_dimensions(): void
    {
        $renderedHome = view('pages.home', [
            'posts' => collect(),
            'stats' => [],
            'totalStress' => null,
            'homepageMarketMovements' => [],
            'homepageTopSales' => [],
            'liveSignalsCount' => 0,
            'marketInsightsCount' => 0,
            'signalTypesCount' => 0,
            'marketInsightSignalCount' => 0,
            'topSignal' => [],
        ])->render();

        $this->assertStringContainsString(
            'src="'.asset('assets/images/site/research-logo-4.png').'"',
            $renderedHome
        );
        $this->assertStringContainsString('width="512"', $renderedHome);
        $this->assertStringContainsString('height="512"', $renderedHome);
        $this->assertStringContainsString(
            'src="'.asset('/assets/images/site/home.jpg').'"',
            $renderedHome
        );
        $this->assertStringContainsString('width="437"', $renderedHome);
        $this->assertStringContainsString('height="300"', $renderedHome);
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
