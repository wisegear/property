<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HpiDashboardApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_the_complete_hpi_dashboard(): void
    {
        $areas = [
            ['K02000001', 'United Kingdom'],
            ['E92000001', 'England'],
            ['S92000003', 'Scotland'],
            ['W92000004', 'Wales'],
            ['N92000002', 'Northern Ireland'],
            ['E06000001', 'Hartlepool'],
        ];

        foreach ($areas as [$code, $name]) {
            DB::table('hpi_monthly')->insert([
                'AreaCode' => $code,
                'Date' => '2026-03-01',
                'RegionName' => $name,
                'AveragePrice' => 250000,
                'Index' => 100,
                'SalesVolume' => 100,
                'DetachedPrice' => 350000,
                'SemiDetachedPrice' => 280000,
                'TerracedPrice' => 230000,
                'FlatPrice' => 200000,
                '1m%Change' => 0.4,
                '12m%Change' => 1.2,
            ]);
        }

        $response = $this->getJson(route('api.v1.hpi.dashboard', absolute: false));

        $response
            ->assertOk()
            ->assertJsonStructure(['data' => [
                'title',
                'description',
                'source_note',
                'latest_date',
                'nations' => ['*' => [
                    'name',
                    'code',
                    'date',
                    'average_price',
                    'one_month_change',
                    'twelve_month_change',
                    'sales_volume',
                ]],
                'annual_change_series',
                'property_type_series',
                'movers',
                'losers',
                'website_url',
            ]])
            ->assertJsonCount(5, 'data.nations')
            ->assertJsonCount(5, 'data.annual_change_series')
            ->assertJsonCount(5, 'data.property_type_series')
            ->assertJsonPath('data.latest_date', '2026-03-01')
            ->assertJsonPath('data.nations.0.name', 'United Kingdom')
            ->assertJsonPath('data.nations.0.average_price', 250000)
            ->assertJsonPath('data.website_url', route('hpi.home'));
    }

    public function test_it_returns_empty_collections_when_hpi_data_is_unavailable(): void
    {
        $this->getJson(route('api.v1.hpi.dashboard', absolute: false))
            ->assertOk()
            ->assertJsonPath('data.latest_date', null)
            ->assertJsonCount(0, 'data.nations')
            ->assertJsonCount(5, 'data.annual_change_series')
            ->assertJsonCount(5, 'data.property_type_series')
            ->assertJsonCount(0, 'data.movers')
            ->assertJsonCount(0, 'data.losers');
    }
}
