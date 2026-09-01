<?php

namespace Tests\Feature;

use Tests\TestCase;

class StampDutyHighValueSurchargeTest extends TestCase
{
    public function test_sdlt_calculation_includes_the_indicative_high_value_surcharge(): void
    {
        $this->postJson('/stamp-duty/calc', [
            'price' => 5_000_000,
            'region' => 'eng-ni',
            'buyer_type' => 'main',
            'additional_property' => false,
            'non_resident' => false,
        ])
            ->assertOk()
            ->assertJsonPath('high_value_council_tax_surcharge.amount', 7_500)
            ->assertJsonPath('high_value_council_tax_surcharge.band', '£5m+')
            ->assertJsonPath('high_value_council_tax_surcharge.latest_sale_price', 5_000_000);
    }

    public function test_sdlt_calculation_returns_no_surcharge_below_the_threshold(): void
    {
        $this->postJson('/stamp-duty/calc', [
            'price' => 1_999_999,
            'region' => 'eng-ni',
            'buyer_type' => 'main',
            'additional_property' => false,
            'non_resident' => false,
        ])
            ->assertOk()
            ->assertJsonPath('high_value_council_tax_surcharge', null);
    }

    public function test_non_sdlt_calculations_do_not_include_the_surcharge(): void
    {
        $this->postJson('/stamp-duty/calc', [
            'price' => 5_000_000,
            'region' => 'scotland',
            'buyer_type' => 'main',
            'additional_property' => false,
            'non_resident' => false,
        ])
            ->assertOk()
            ->assertJsonMissingPath('high_value_council_tax_surcharge');
    }

    public function test_calculator_page_explains_that_the_surcharge_only_applies_in_england(): void
    {
        $response = $this->get('/stamp-duty');

        $response
            ->assertOk()
            ->assertSee('High Value Council Tax Surcharge')
            ->assertSee('England only')
            ->assertSee('It does not apply to properties in Northern Ireland')
            ->assertSee('separate 2026 Valuation Office valuation');
    }
}
