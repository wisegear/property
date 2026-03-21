<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $renderedLayout = view('layouts.app')->render();

        $this->assertStringContainsString(
            'https://wa.me/447720868799?text=Hi%20Lee%2C%20I%27m%20contacting%20you%20about%20propertyresearch.uk',
            $renderedLayout
        );
        $insightsUrl = url('/insights');
        $rentalUrl = url('/rental');
        $repossessionsUrl = url('/repossessions');

        $this->assertStringNotContainsString('https://x.com/Propertyda03', $renderedLayout);
        $this->assertStringContainsString(sprintf('href="%s"', $insightsUrl), $renderedLayout);
        $this->assertSame(2, substr_count($renderedLayout, sprintf('href="%s"', $insightsUrl)));
        $this->assertSame(2, substr_count($renderedLayout, sprintf('href="%s"', $rentalUrl)));
        $this->assertSame(2, substr_count($renderedLayout, sprintf('href="%s"', $repossessionsUrl)));

        $desktopPropertyMenu = strstr($renderedLayout, 'id="propertyDropdown"', true) === false
            ? ''
            : explode('<!-- Social Housing Dropdown Menu -->', explode('id="propertyDropdown"', $renderedLayout, 2)[1], 2)[0];

        $mobilePropertyMenu = strstr($renderedLayout, 'id="mobilePropertyMenu"', true) === false
            ? ''
            : explode('<!-- Social Housing Dropdown (Mobile) -->', explode('id="mobilePropertyMenu"', $renderedLayout, 2)[1], 2)[0];

        $this->assertStringContainsString(sprintf('href="%s"', $rentalUrl), $desktopPropertyMenu);
        $this->assertStringContainsString(sprintf('href="%s"', $repossessionsUrl), $desktopPropertyMenu);
        $this->assertStringContainsString(sprintf('href="%s"', $rentalUrl), $mobilePropertyMenu);
        $this->assertStringContainsString(sprintf('href="%s"', $repossessionsUrl), $mobilePropertyMenu);
        $this->assertStringContainsString('Market Insights', $renderedLayout);
        $this->assertStringContainsString('County Insights', $renderedLayout);
    }
}
