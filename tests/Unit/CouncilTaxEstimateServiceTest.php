<?php

namespace Tests\Unit;

use App\Services\CouncilTaxEstimateService;
use PHPUnit\Framework\TestCase;

class CouncilTaxEstimateServiceTest extends TestCase
{
    public function test_it_returns_a_conservative_english_band_and_charge_range(): void
    {
        $estimate = (new CouncilTaxEstimateService)->fromValuations([54000], 'E92000001');

        $this->assertNotNull($estimate);
        $this->assertSame('B', $estimate['low_band']);
        $this->assertSame('C', $estimate['high_band']);
        $this->assertSame('Bands B–C', $estimate['band_label']);
        $this->assertSame(1860, $estimate['low_annual']);
        $this->assertSame(2126, $estimate['high_annual']);
        $this->assertSame(1991, $estimate['valuation_year']);
    }

    public function test_it_uses_the_local_authority_charge_when_available(): void
    {
        $estimate = (new CouncilTaxEstimateService)->fromValuations(
            valuations: [450000],
            countryCode: 'E92000001',
            bandDCharge: 1643.44,
            authority: 'Kensington and Chelsea',
        );

        $this->assertNotNull($estimate);
        $this->assertSame('Band H', $estimate['band_label']);
        $this->assertSame(3287, $estimate['low_annual']);
        $this->assertSame(3287, $estimate['high_annual']);
        $this->assertSame('Kensington and Chelsea average', $estimate['rate_basis']);
    }

    public function test_multiple_sales_use_the_median_and_a_narrower_uncertainty_margin(): void
    {
        $estimate = (new CouncilTaxEstimateService)->fromValuations([50000, 52000, 100000], 'E92000001');

        $this->assertNotNull($estimate);
        $this->assertSame(52000.0, $estimate['estimated_valuation']);
        $this->assertSame('B', $estimate['low_band']);
        $this->assertSame('C', $estimate['high_band']);
        $this->assertSame(3, $estimate['sales_used']);
    }

    public function test_it_does_not_estimate_an_unsupported_country(): void
    {
        $this->assertNull((new CouncilTaxEstimateService)->fromValuations([54000], 'S92000003'));
    }
}
