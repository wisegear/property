<?php

namespace Tests\Feature;

use Illuminate\Support\Carbon;
use Tests\TestCase;

class InflationHeroImageTest extends TestCase
{
    public function test_inflation_hero_uses_wage_growth_image(): void
    {
        $all = collect([
            (object) [
                'date' => Carbon::parse('2025-01-01'),
                'rate' => 2.5,
            ],
        ]);

        $view = $this->view('inflation.index', [
            'all' => $all,
            'latest' => $all->last(),
            'previous' => null,
            'labels' => ['2025-01-01'],
            'values' => [2.5],
            'yearly' => collect(),
        ]);

        $view->assertSee('assets/images/site/wage_growth.jpg', false);
    }
}
