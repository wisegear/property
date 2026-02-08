<?php

namespace Tests\Feature;

use Tests\TestCase;

class MortgageApprovalsHeroImageTest extends TestCase
{
    public function test_mortgage_approvals_hero_uses_approvals_image(): void
    {
        $emptySeries = [
            'labels' => collect(),
            'values' => collect(),
            'latest' => null,
            'prev' => null,
            'delta' => null,
        ];

        $view = $this->view('mortgages.home', [
            'seriesData' => [
                'LPMVTVX' => $emptySeries,
                'LPMB4B3' => $emptySeries,
                'LPMB4B4' => $emptySeries,
                'LPMB3C8' => $emptySeries,
            ],
            'latestPeriod' => null,
            'table' => [],
            'yearTable' => [],
        ]);

        $view->assertSee('assets/images/site/approvals.jpg', false);
    }
}
