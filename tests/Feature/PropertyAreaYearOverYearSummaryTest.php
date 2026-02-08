<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PropertyAreaYearOverYearSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_area_page_shows_increased_summary_when_change_is_at_least_one_percent(): void
    {
        [$type, $name] = $this->firstArea();
        $this->insertAreaSale($type, $name, 100000, now()->subMonths(18));
        $this->insertAreaSale($type, $name, 100000, now()->subMonths(16));
        $this->insertAreaSale($type, $name, 120000, now()->subMonths(8));
        $this->insertAreaSale($type, $name, 120000, now()->subMonths(2));

        $response = $this->requestAreaPage($type, $name);

        $response->assertOk();
        $response->assertSee("House prices in {$name} have increased by 20.0% over the past 12 months, based on Land Registry sales data.");
    }

    public function test_area_page_shows_fallen_summary_when_change_is_at_most_negative_one_percent(): void
    {
        [$type, $name] = $this->firstArea();
        $this->insertAreaSale($type, $name, 100000, now()->subMonths(18));
        $this->insertAreaSale($type, $name, 100000, now()->subMonths(16));
        $this->insertAreaSale($type, $name, 95000, now()->subMonths(8));
        $this->insertAreaSale($type, $name, 95000, now()->subMonths(2));

        $response = $this->requestAreaPage($type, $name);

        $response->assertOk();
        $response->assertSee("House prices in {$name} have fallen by 5.0% over the past 12 months, based on Land Registry sales data.");
    }

    public function test_area_page_shows_flat_summary_when_change_is_within_one_percent(): void
    {
        [$type, $name] = $this->firstArea();
        $this->insertAreaSale($type, $name, 100000, now()->subMonths(18));
        $this->insertAreaSale($type, $name, 100000, now()->subMonths(16));
        $this->insertAreaSale($type, $name, 100500, now()->subMonths(8));
        $this->insertAreaSale($type, $name, 100500, now()->subMonths(2));

        $response = $this->requestAreaPage($type, $name);

        $response->assertOk();
        $response->assertSee("House prices in {$name} have remained broadly flat over the past 12 months, based on Land Registry sales data.");
    }

    public function test_area_page_shows_fallback_when_year_over_year_data_is_unavailable(): void
    {
        [$type, $name] = $this->firstArea();
        $this->insertAreaSale($type, $name, 200000, now()->subMonths(2));

        $response = $this->requestAreaPage($type, $name);

        $response->assertOk();
        $response->assertSee("House price movement over the past year for {$name} is not available, based on current Land Registry data.");
    }

    private function requestAreaPage(string $type, string $name)
    {
        Cache::flush();

        return $this->get(route('property.area.show', [
            'type' => $type,
            'slug' => Str::slug($name),
        ], absolute: false));
    }

    private function firstArea(): array
    {
        $areas = json_decode(file_get_contents(public_path('data/property_districts.json')), true) ?? [];
        $selected = collect($areas)->first(function ($item) {
            return is_array($item)
                && in_array(($item['type'] ?? ''), ['locality', 'town', 'district', 'county'], true)
                && ! empty($item['name'] ?? $item['label'] ?? null);
        });

        if (! is_array($selected)) {
            $this->markTestSkipped('No area entry available in property_districts.json.');
        }

        return [
            (string) $selected['type'],
            (string) ($selected['name'] ?? $selected['label']),
        ];
    }

    private function insertAreaSale(string $type, string $name, int $price, mixed $date): void
    {
        $columns = [
            'locality' => 'Locality',
            'town' => 'TownCity',
            'district' => 'District',
            'county' => 'County',
        ];

        $areaColumns = [
            'Locality' => null,
            'TownCity' => null,
            'District' => null,
            'County' => null,
        ];

        $areaColumns[$columns[$type]] = $name;

        DB::table('land_registry')->insert(array_merge([
            'TransactionID' => (string) Str::uuid(),
            'Price' => $price,
            'Date' => $date,
            'Postcode' => 'WR53EU',
            'PropertyType' => 'D',
            'NewBuild' => 'N',
            'Duration' => 'F',
            'PAON' => '1',
            'SAON' => null,
            'Street' => 'Test Street',
            'PPDCategoryType' => 'A',
            'RecordStatus' => 'A',
        ], $areaColumns));
    }
}
