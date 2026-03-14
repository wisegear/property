<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EpcSearchLayoutTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.key', 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=');
    }

    #[Test]
    public function england_and_wales_search_page_places_the_postcode_form_above_the_map(): void
    {
        $response = $this->get('/epc/search');

        $response->assertOk();
        $response->assertSee('Enter a postcode below to view EPC certificates.', false);
        $response->assertSee('id="epc-map"', false);

        $content = $response->getContent();

        $this->assertIsString($content);
        $this->assertLessThan(
            strpos($content, 'id="epc-map"'),
            strpos($content, 'Enter a postcode below to view EPC certificates.')
        );
    }

    #[Test]
    public function scotland_search_page_places_the_postcode_form_above_the_map(): void
    {
        $response = $this->get('/epc/search_scotland');

        $response->assertOk();
        $response->assertSee('Enter a postcode below to view EPC certificates.', false);
        $response->assertSee('id="epc-map-scotland"', false);

        $content = $response->getContent();

        $this->assertIsString($content);
        $this->assertLessThan(
            strpos($content, 'id="epc-map-scotland"'),
            strpos($content, 'Enter a postcode below to view EPC certificates.')
        );
    }
}
