<?php

namespace Tests\Feature;

use Tests\TestCase;

class InterestRatesHeroImageTest extends TestCase
{
    public function test_interest_rates_hero_uses_boe_rates_image(): void
    {
        $view = $this->view('interest.home', [
            'rates' => collect(),
        ]);

        $view->assertSee('assets/images/site/boe_rates.jpg', false);
    }
}
