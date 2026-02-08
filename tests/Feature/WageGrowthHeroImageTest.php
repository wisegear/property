<?php

namespace Tests\Feature;

use Illuminate\Support\Carbon;
use Tests\TestCase;

class WageGrowthHeroImageTest extends TestCase
{
    public function test_wage_growth_hero_uses_wage_growth_image(): void
    {
        $all = collect([
            (object) [
                'date' => Carbon::parse('2025-01-01'),
                'single_month_yoy' => 4.1,
                'three_month_avg_yoy' => 3.9,
            ],
        ]);

        $view = $this->view('wage_growth.index', [
            'all' => $all,
            'latest' => $all->last(),
            'previous' => null,
            'labels' => ['2025-01-01'],
            'values_single' => [4.1],
            'values_three' => [3.9],
        ]);

        $view->assertSee('assets/images/site/wage_growth.jpg', false);
    }
}
