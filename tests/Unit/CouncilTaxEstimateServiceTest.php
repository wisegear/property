<?php

namespace Tests\Unit;

use App\Services\CouncilTaxEstimateService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CouncilTaxEstimateServiceTest extends TestCase
{
    #[DataProvider('highValueSurchargeProvider')]
    public function test_it_calculates_the_high_value_council_tax_surcharge_boundaries(
        ?int $salePrice,
        string $countryCode,
        ?int $expectedAmount,
        ?string $expectedBand,
        bool $shouldReturnSurcharge,
    ): void {
        $surcharge = (new CouncilTaxEstimateService)->getHighValueCouncilTaxSurcharge($salePrice, $countryCode);

        if (! $shouldReturnSurcharge) {
            $this->assertNull($surcharge);

            return;
        }

        $this->assertNotNull($surcharge);
        $this->assertSame($expectedAmount, $surcharge['amount']);
        $this->assertSame($expectedBand, $surcharge['band']);
        $this->assertSame($salePrice, $surcharge['latest_sale_price']);
    }

    /** @return array<string, array{int|null, string, int|null, string|null, bool}> */
    public static function highValueSurchargeProvider(): array
    {
        return [
            'below threshold' => [1_999_999, 'E92000001', null, null, false],
            'lower boundary' => [2_000_000, 'E92000001', 2_500, '£2m–£2.5m', true],
            'within first band' => [2_250_000, 'E92000001', 2_500, '£2m–£2.5m', true],
            'upper edge of first band' => [2_499_999, 'E92000001', 2_500, '£2m–£2.5m', true],
            'second band boundary' => [2_500_000, 'E92000001', 3_500, '£2.5m–£3.5m', true],
            'third band boundary' => [3_500_000, 'E92000001', 5_000, '£3.5m–£5m', true],
            'top band boundary' => [5_000_000, 'E92000001', 7_500, '£5m+', true],
            'Scotland' => [2_500_000, 'S92000003', null, null, false],
            'Wales' => [2_500_000, 'W92000004', null, null, false],
            'no sale price' => [null, 'E92000001', null, null, false],
        ];
    }

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
