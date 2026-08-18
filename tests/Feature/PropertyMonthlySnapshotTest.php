<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PropertyMonthlySnapshotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_latest_month_snapshot_uses_category_a_sales_only(): void
    {
        DB::table('land_registry')->insert([
            $this->transaction('one', 200000, '2026-05-10', 'T', 'N', 'F', 'A'),
            $this->transaction('two', 100000, '2026-06-02', 'D', 'Y', 'F', 'A'),
            $this->transaction('three', 300000, '2026-06-12', 'F', 'N', 'L', 'A'),
            $this->transaction('four', 900000, '2026-06-15', 'S', 'N', 'F', 'B'),
        ]);

        $this->get('/property/monthly-snapshot')
            ->assertOk()
            ->assertSee('June 2026')
            ->assertSee('£200,000')
            ->assertSee('Detached')
            ->assertSee('Flat / maisonette')
            ->assertSee('New build vs existing')
            ->assertSee('Freehold vs leasehold')
            ->assertSee('Where sales were recorded')
            ->assertSee('Sales by price band')
            ->assertSee('Most active districts')
            ->assertSee('Notable sales')
            ->assertSee('HM Land Registry routinely backfills its latest three months')
            ->assertDontSee('Upload progress');
    }

    public function test_property_dashboard_links_to_latest_month_snapshot(): void
    {
        DB::table('land_registry')->insert(
            $this->transaction('one', 250000, '2026-06-10', 'T', 'N', 'F', 'A')
        );

        $this->get('/property')
            ->assertOk()
            ->assertSee(route('property.monthly-snapshot', absolute: false), false)
            ->assertSee('Latest month snapshot');
    }

    /**
     * @return array<string, mixed>
     */
    private function transaction(
        string $id,
        int $price,
        string $date,
        string $propertyType,
        string $newBuild,
        string $duration,
        string $category,
    ): array {
        return [
            'TransactionID' => str_pad($id, 36, '0'),
            'Price' => $price,
            'Date' => $date,
            'Postcode' => 'AB1 1AA',
            'PropertyType' => $propertyType,
            'NewBuild' => $newBuild,
            'Duration' => $duration,
            'PAON' => '1',
            'Street' => 'HIGH STREET',
            'District' => 'TEST DISTRICT',
            'County' => 'TEST COUNTY',
            'PPDCategoryType' => $category,
        ];
    }
}
