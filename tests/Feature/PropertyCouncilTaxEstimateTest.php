<?php

namespace Tests\Feature;

use App\Services\CouncilTaxEstimateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PropertyCouncilTaxEstimateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    }

    public function test_property_page_displays_an_automatic_council_tax_estimate(): void
    {
        DB::table('land_registry')->insert([
            'TransactionID' => '11111111-1111-1111-1111-111111111111',
            'Price' => 240000,
            'Date' => '2025-03-15 00:00:00',
            'Postcode' => 'AB1 2CD',
            'PropertyType' => 'D',
            'NewBuild' => 'N',
            'Duration' => 'F',
            'PAON' => '10',
            'Street' => 'MARKET ROAD',
            'Locality' => 'LOCAL',
            'TownCity' => 'TOWN',
            'District' => 'DISTRICT',
            'County' => 'COUNTY',
            'PPDCategoryType' => 'A',
        ]);
        DB::table('onspd_v2')->insert([
            'pcds' => 'AB1 2CD',
            'ctry25cd' => 'E92000001',
            'rgn25cd' => 'E12000001',
            'lad25cd' => 'E09000020',
        ]);
        DB::table('hpi_monthly')->insert([
            $this->hpiRow('E92000001', '1991-01-04', 18),
            $this->hpiRow('E92000001', '1995-01-01', 20),
            $this->hpiRow('E12000001', '1995-01-01', 40),
            $this->hpiRow('E12000001', '2025-01-03', 160),
        ]);

        $this->get('/property/ab1-2cd-10-market-road')
            ->assertOk()
            ->assertSee('Estimated Council Tax')
            ->assertSee('£1,278–£1,461', false)
            ->assertSee('Likely Bands B–C · standard two-adult charge')
            ->assertSee('Kensington and Chelsea average Council Tax charges')
            ->assertSee("This is an estimate, not the property's official band or bill.", false)
            ->assertViewHas('councilTaxEstimate', function (?array $estimate): bool {
                return $estimate !== null
                    && $estimate['low_band'] === 'B'
                    && $estimate['high_band'] === 'C'
                    && $estimate['authority'] === 'Kensington and Chelsea';
            });
    }

    public function test_recent_category_a_sale_uses_the_latest_available_hpi_month(): void
    {
        DB::table('onspd_v2')->insert([
            'pcds' => 'W8 6AH',
            'ctry25cd' => 'E92000001',
            'rgn25cd' => 'E12000007',
            'lad25cd' => 'E09000020',
        ]);
        DB::table('hpi_monthly')->insert([
            $this->hpiRow('E92000001', '1991-01-04', 18.3),
            $this->hpiRow('E92000001', '1995-01-01', 17.6),
            $this->hpiRow('E12000007', '1995-01-01', 14),
            $this->hpiRow('E12000007', '2026-01-03', 95),
            $this->hpiRow('E12000007', '2013-01-10', 80),
        ]);

        $estimate = app(CouncilTaxEstimateService::class)->forProperty(collect([
            (object) ['Price' => 7700000, 'Date' => '2026-04-10', 'PPDCategoryType' => 'A'],
            (object) ['Price' => 10100000, 'Date' => '2013-10-15', 'PPDCategoryType' => 'B'],
        ]), 'W8 6AH');

        $this->assertNotNull($estimate);
        $this->assertSame(1, $estimate['sales_used']);
        $this->assertSame('Band H', $estimate['band_label']);
    }

    public function test_category_b_sale_is_used_when_no_category_a_valuation_is_available(): void
    {
        DB::table('onspd_v2')->insert([
            'pcds' => 'W8 6AH',
            'ctry25cd' => 'E92000001',
            'rgn25cd' => 'E12000007',
            'lad25cd' => 'E09000020',
        ]);
        DB::table('hpi_monthly')->insert([
            $this->hpiRow('E92000001', '1991-01-04', 18.3),
            $this->hpiRow('E92000001', '1995-01-01', 17.6),
            $this->hpiRow('E12000007', '1995-01-01', 14),
            $this->hpiRow('E12000007', '2013-01-10', 80),
        ]);

        $estimate = app(CouncilTaxEstimateService::class)->forProperty(collect([
            (object) ['Price' => 10100000, 'Date' => '2013-10-15', 'PPDCategoryType' => 'B'],
        ]), 'W8 6AH');

        $this->assertNotNull($estimate);
        $this->assertSame(1, $estimate['sales_used']);
        $this->assertSame('Band H', $estimate['band_label']);
    }

    /** @return array<string, mixed> */
    private function hpiRow(string $areaCode, string $date, float $index): array
    {
        return [
            'AreaCode' => $areaCode,
            'Date' => $date,
            'RegionName' => $areaCode,
            'Index' => $index,
        ];
    }
}
