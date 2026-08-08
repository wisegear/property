<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PublicLegalPagesTest extends TestCase
{
    public static function publicPageProvider(): array
    {
        return [
            'app privacy policy' => ['/privacy/app', 'PropertyResearch.uk App Privacy Policy'],
            'data sources' => ['/data-sources', 'Data Sources &amp; Licensing'],
            'terms' => ['/terms', 'Terms &amp; Disclaimers'],
            'support' => ['/support', 'App Support'],
            'legal hub' => ['/legal', 'Legal &amp; Support'],
        ];
    }

    #[DataProvider('publicPageProvider')]
    public function test_legal_pages_are_publicly_accessible(string $url, string $heading): void
    {
        $this->get($url)
            ->assertOk()
            ->assertSee($heading, false);
    }

    public function test_data_sources_page_contains_required_ogl_attribution(): void
    {
        $this->get('/data-sources')
            ->assertOk()
            ->assertSeeText('Contains public sector information licensed under the Open Government Licence v3.0')
            ->assertSee('https://www.nationalarchives.gov.uk/doc/open-government-licence/version/3/', false);
    }

    public function test_privacy_page_contains_core_app_and_api_disclosures(): void
    {
        $this->get('/privacy/app')
            ->assertOk()
            ->assertSeeText('does not require a user account')
            ->assertSeeText('does not contain advertising or third-party analytics SDKs')
            ->assertSeeText('transmitted securely to the PropertyResearch.uk API')
            ->assertSeeText('IP address')
            ->assertSeeText('website uses Google Analytics')
            ->assertSeeText('property address is sent to Apple Maps for geocoding')
            ->assertSeeText('does not request or transmit your current location')
            ->assertSee('https://www.apple.com/legal/privacy/', false);
    }

    public function test_support_page_contains_the_approved_public_contact_methods(): void
    {
        $this->get('/support')
            ->assertOk()
            ->assertSee('https://wa.me/447720868799', false)
            ->assertSee('mailto:lee@wisener.net', false)
            ->assertSeeText('lee@wisener.net');
    }

    public function test_footer_links_to_the_legal_hub(): void
    {
        $this->get('/privacy/app')
            ->assertOk()
            ->assertSee(route('legal.index'), false)
            ->assertSeeText('Legal and support');
    }
}
