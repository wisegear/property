<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LocalAuthorityControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureEnglandCouncilHousingStockTableExists();
    }

    public function test_england_page_aggregates_rows_and_orders_financial_years_chronologically(): void
    {
        DB::table('england_council_housing_stock')->insert([
            [
                'year' => '2020-21',
                'region_name' => 'North West',
                'total_stock' => 100,
                'new_builds' => 5,
                'acquisitions' => 1,
            ],
            [
                'year' => '2019-20',
                'region_name' => 'North West',
                'total_stock' => 90,
                'new_builds' => 4,
                'acquisitions' => 2,
            ],
            [
                'year' => '2020-21',
                'region_name' => 'North West',
                'total_stock' => 10,
                'new_builds' => 1,
                'acquisitions' => 0,
            ],
            [
                'year' => '2020-21',
                'region_name' => 'South East',
                'total_stock' => 200,
                'new_builds' => 8,
                'acquisitions' => 3,
            ],
            [
                'year' => '2019-20',
                'region_name' => 'South East',
                'total_stock' => 180,
                'new_builds' => 7,
                'acquisitions' => 2,
            ],
        ]);

        $response = $this->get(route('localauthority.england', absolute: false));

        $response->assertOk();
        $this->assertSame(['2019-20', '2020-21'], $response->viewData('years'));
        $this->assertSame(['North West', 'South East'], $response->viewData('regions'));

        $byRegion = $response->viewData('byRegion');
        $this->assertSame(110, $byRegion['North West']['2020-21']['total_stock']);
        $this->assertSame(310, $response->viewData('national')['2020-21']['total_stock']);
    }

    protected function ensureEnglandCouncilHousingStockTableExists(): void
    {
        if (! Schema::hasTable('england_council_housing_stock')) {
            Schema::create('england_council_housing_stock', function (Blueprint $table) {
                $table->id();
                $table->string('year')->nullable();
                $table->string('region_name')->nullable();
                $table->integer('total_stock')->nullable();
                $table->integer('new_builds')->nullable();
                $table->integer('acquisitions')->nullable();
                $table->timestamps();
            });
        }
    }
}
