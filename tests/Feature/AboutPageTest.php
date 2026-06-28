<?php

namespace Tests\Feature;

use Tests\TestCase;

class AboutPageTest extends TestCase
{
    public function test_about_page_uses_professional_independent_positioning(): void
    {
        $response = $this->get('/about');

        $response->assertOk();
        $response->assertSeeText('Independent UK Property Research');
        $response->assertSeeText('Why Trust PropertyResearch.uk?');
        $response->assertSeeText('Our Mission');
        $response->assertSeeText('Methodology');
        $response->assertSeeText('Independent by Design');
        $response->assertSeeText('About Lee');
        $response->assertSeeText('PropertyResearch.uk continues to evolve as new official datasets become available and new ideas emerge.');

        $response->assertDontSeeText('repeat offender');
        $response->assertDontSeeText('tidy corner');
        $response->assertDontSeeText('personal project');
        $response->assertDontSeeText('No fees, no catch');
        $response->assertDontSeeText('heckling welcome');
    }
}
