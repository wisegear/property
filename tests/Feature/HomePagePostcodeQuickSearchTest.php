<?php

namespace Tests\Feature;

use Tests\TestCase;

class HomePagePostcodeQuickSearchTest extends TestCase
{
    public function test_home_page_shows_quick_postcode_search_form_above_market_stress_panel(): void
    {
        $view = $this->view('pages.home', [
            'posts' => collect(),
            'stats' => [
                'property_records' => 0,
                'uk_avg_price' => 0,
                'uk_avg_rent' => 0,
                'bank_rate' => 0,
                'inflation_rate' => 0,
                'epc_count' => 0,
            ],
            'totalStress' => 10,
        ]);

        $searchUrl = route('property.search', absolute: false);

        $view->assertSee('Quick postcode search');
        $view->assertSee($searchUrl, false);
        $view->assertSee('name="postcode"', false);
        $view->assertSee('placeholder="e.g. SW7 5PH"', false);
        $view->assertSeeInOrder(['Quick postcode search', 'Market Stress Score guide']);
    }
}
