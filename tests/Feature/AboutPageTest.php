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
        $response->assertSeeText('Why the platform exists');
        $response->assertSeeText('Why trust PropertyResearch.uk?');
        $response->assertSeeText('What the platform covers');
        $response->assertSeeText('How the data is handled');
        $response->assertSeeText('About the creator');
        $response->assertSeeText('PropertyResearch.uk continues to evolve as new datasets become available and new ideas emerge.');
        $response->assertSeeText('PropertyResearch.uk helps people understand the UK property market through official data, clear analysis and practical tools.');
        $response->assertSeeText('PropertyResearch.uk was created and is maintained by Lee Wisener');

        $response->assertDontSeeText('repeat offender');
        $response->assertDontSeeText('tidy corner');
        $response->assertDontSeeText('personal project');
        $response->assertDontSeeText('01');
        $response->assertDontSeeText('Closing Statement');
        $response->assertDontSeeText('creator of PropertyResearch.uk.');
    }
}
