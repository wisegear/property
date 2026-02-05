<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PropertyControllerPostgresCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_property_home_route_loads_with_postgres_safe_queries(): void
    {
        DB::table('land_registry')->insert([
            $this->landRegistryRow(
                transactionId: '11111111-1111-1111-1111-11111111111111',
                price: 250000,
                date: '2024-01-15 00:00:00',
                postcode: 'AB1 2CD',
                paon: '1',
                street: 'HIGH STREET'
            ),
        ]);

        $this->get('/property')->assertOk();
    }

    public function test_property_show_route_loads_without_mysql_index_hints(): void
    {
        DB::table('land_registry')->insert([
            $this->landRegistryRow(
                transactionId: '22222222-2222-2222-2222-22222222222222',
                price: 350000,
                date: '2025-03-01 00:00:00',
                postcode: 'AB1 2CD',
                paon: '10',
                street: 'MARKET ROAD'
            ),
        ]);

        DB::table('onspd')->insert([
            'pcds' => 'AB1 2CD',
            'lat' => 52.123456,
            'long' => -1.123456,
        ]);

        $this->get('/property/show?postcode=AB1%202CD&paon=10&street=MARKET ROAD')
            ->assertOk();
    }

    private function landRegistryRow(
        string $transactionId,
        int $price,
        string $date,
        string $postcode,
        string $paon,
        string $street
    ): array {
        $row = [
            'TransactionID' => $transactionId,
            'Price' => $price,
            'Date' => $date,
            'Postcode' => $postcode,
            'PAON' => $paon,
            'Street' => $street,
            'PropertyType' => 'D',
            'NewBuild' => 'N',
            'Duration' => 'F',
            'Locality' => 'LOCAL',
            'TownCity' => 'TOWN',
            'District' => 'DISTRICT',
            'County' => 'COUNTY',
            'PPDCategoryType' => 'A',
        ];

        if (Schema::hasColumn('land_registry', 'YearDate')) {
            $row['YearDate'] = (int) date('Y', strtotime($date));
        }

        return $row;
    }
}
