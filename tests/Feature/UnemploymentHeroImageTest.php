<?php

namespace Tests\Feature;

use Illuminate\Support\Carbon;
use Tests\TestCase;

class UnemploymentHeroImageTest extends TestCase
{
    public function test_unemployment_hero_uses_unemployment_image(): void
    {
        $series = collect([
            (object) [
                'date' => Carbon::parse('2025-01-01'),
                'rate' => 4.3,
            ],
        ]);

        $view = $this->view('unemployment.index', [
            'series' => $series,
            'latest' => $series->last(),
            'previousYear' => null,
            'yearOnYearDelta' => null,
            'labels' => json_encode(['Jan 2025']),
            'values' => json_encode([4.3]),
        ]);

        $view->assertSee('assets/images/site/unemployment.jpg', false);
    }
}
