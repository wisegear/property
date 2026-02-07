<?php

namespace Tests\Feature;

use App\Http\Controllers\PropertyAreaController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PropertyAreaControllerMedianTest extends TestCase
{
    use RefreshDatabase;

    public function test_build_area_payload_uses_median_for_price_series(): void
    {
        DB::table('land_registry')->insert([
            $this->landRegistryRow('11111111-1111-1111-1111-11111111111111', 100000, '2024-01-15 00:00:00'),
            $this->landRegistryRow('22222222-2222-2222-2222-22222222222222', 200000, '2024-02-15 00:00:00'),
            $this->landRegistryRow('33333333-3333-3333-3333-33333333333333', 300000, '2024-03-15 00:00:00'),
            $this->landRegistryRow('44444444-4444-4444-4444-44444444444444', 1000000, '2024-04-15 00:00:00'),
        ]);

        $payload = app(PropertyAreaController::class)->buildAreaPayload('District', 'ALPHA');

        $expected = DB::connection()->getDriverName() === 'pgsql' ? 250000 : 400000;

        $this->assertSame($expected, (int) ($payload['summary']->avg_price ?? 0));
        $this->assertSame($expected, (int) ($payload['byYear']->first()->avg_price ?? 0));
        $this->assertSame($expected, (int) ($payload['byType']['detached']['series']->first()->avg_price ?? 0));
    }

    private function landRegistryRow(string $transactionId, int $price, string $date): array
    {
        $row = [
            'TransactionID' => $transactionId,
            'Price' => $price,
            'Date' => $date,
            'Postcode' => 'AB1 2CD',
            'PAON' => '1',
            'Street' => 'HIGH STREET',
            'PropertyType' => 'D',
            'NewBuild' => 'N',
            'Duration' => 'F',
            'Locality' => 'LOCAL',
            'TownCity' => 'TOWN',
            'District' => 'ALPHA',
            'County' => 'COUNTY',
            'PPDCategoryType' => 'A',
        ];

        if (Schema::hasColumn('land_registry', 'YearDate')) {
            $row['YearDate'] = (int) date('Y', strtotime($date));
        }

        return $row;
    }
}
