<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ScottishPricesControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        Cache::flush();
        Schema::dropIfExists('scottish_property_prices');

        Schema::create('scottish_property_prices', function (Blueprint $table): void {
            $table->id();
            $table->string('month');
            $table->string('local_authority');
            $table->string('local_authority_code', 12);
            $table->unsignedInteger('volume_of_residential_property_sales')->nullable();
            $table->unsignedInteger('mean_residential_property_price')->nullable();
            $table->unsignedInteger('median_residential_property_price')->nullable();
            $table->unsignedBigInteger('value_of_residential_property_sales')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Cache::flush();
        Schema::dropIfExists('scottish_property_prices');

        parent::tearDown();
    }

    public function test_scottish_prices_page_renders_scotland_wide_yearly_data_and_caches_it(): void
    {
        $this->seedScottishPropertyPrices();

        $response = $this->get(route('property.scottish-prices', absolute: false));

        $response->assertOk();
        $response->assertSee('Scottish Prices');
        $response->assertSee('Explore yearly Scottish residential property data across the whole of Scotland or focus on an individual local authority.');
        $response->assertViewHas('selectedAuthority', null);
        $response->assertViewHas('localAuthorities', ['Aberdeen City', 'Dundee City']);
        $response->assertViewHas('years', [2003, 2004]);
        $response->assertViewHas('meanPrices', [110000.0, 135000.0]);
        $response->assertViewHas('medianPrices', [95000.0, 111000.0]);
        $response->assertViewHas('salesVolumes', [30, 36]);
        $response->assertViewHas('salesValues', [3160000.0, 4890000.0]);
        $response->assertViewHas('stats', function (array $stats): bool {
            return $stats['latestYear'] === 2004
                && $stats['latestMeanPrice'] === 135000.0
                && $stats['latestMedianPrice'] === 111000.0
                && $stats['latestSalesVolume'] === 36
                && $stats['latestSalesValue'] === 4890000.0;
        });

        $this->assertSame(['Aberdeen City', 'Dundee City'], Cache::get('scottish_prices:authorities'));
        $this->assertSame([
            'years' => [2003, 2004],
            'meanPrices' => [110000.0, 135000.0],
            'medianPrices' => [95000.0, 111000.0],
            'salesVolumes' => [30, 36],
            'salesValues' => [3160000.0, 4890000.0],
        ], Cache::get('scottish_prices:scotland'));
    }

    public function test_scottish_prices_page_filters_to_a_local_authority_and_caches_that_dataset(): void
    {
        $this->seedScottishPropertyPrices();

        $response = $this->get(route('property.scottish-prices', ['local_authority' => '  Aberdeen City  '], false));

        $response->assertOk();
        $response->assertSee('Price Trend · Aberdeen City');
        $response->assertViewHas('selectedAuthority', 'Aberdeen City');
        $response->assertViewHas('years', [2003, 2004]);
        $response->assertViewHas('meanPrices', [105000.0, 132500.0]);
        $response->assertViewHas('medianPrices', [92500.0, 109000.0]);
        $response->assertViewHas('salesVolumes', [22, 21]);
        $response->assertViewHas('salesValues', [2200000.0, 2760000.0]);

        $this->assertSame([
            'years' => [2003, 2004],
            'meanPrices' => [105000.0, 132500.0],
            'medianPrices' => [92500.0, 109000.0],
            'salesVolumes' => [22, 21],
            'salesValues' => [2200000.0, 2760000.0],
        ], Cache::get('scottish_prices:la:'.md5('aberdeen city')));
    }

    public function test_scottish_prices_page_ignores_unknown_authority_and_layout_includes_nav_links(): void
    {
        $this->seedScottishPropertyPrices();

        $response = $this->get(route('property.scottish-prices', ['local_authority' => 'Unknown Council'], false));

        $response->assertOk();
        $response->assertViewHas('selectedAuthority', null);
        $response->assertSee('Explore yearly Scottish residential property data across the whole of Scotland or focus on an individual local authority.');

        $renderedLayout = view('layouts.app')->render();
        $url = route('property.scottish-prices', absolute: false);

        $this->assertSame(2, substr_count($renderedLayout, sprintf('href="%s"', $url)));
        $this->assertStringContainsString('Scottish House Prices', $renderedLayout);
    }

    private function seedScottishPropertyPrices(): void
    {
        DB::table('scottish_property_prices')->insert([
            [
                'month' => 'April 2003',
                'local_authority' => 'Aberdeen City',
                'local_authority_code' => 'S12000033',
                'volume_of_residential_property_sales' => 10,
                'mean_residential_property_price' => 100000,
                'median_residential_property_price' => 90000,
                'value_of_residential_property_sales' => 1000000,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'month' => 'May 2003',
                'local_authority' => 'Aberdeen City',
                'local_authority_code' => 'S12000033',
                'volume_of_residential_property_sales' => 12,
                'mean_residential_property_price' => 110000,
                'median_residential_property_price' => 95000,
                'value_of_residential_property_sales' => 1200000,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'month' => 'June 2003',
                'local_authority' => 'Dundee City',
                'local_authority_code' => 'S12000042',
                'volume_of_residential_property_sales' => 8,
                'mean_residential_property_price' => 120000,
                'median_residential_property_price' => 100000,
                'value_of_residential_property_sales' => 960000,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'month' => 'January 2004',
                'local_authority' => 'Aberdeen City',
                'local_authority_code' => 'S12000033',
                'volume_of_residential_property_sales' => 9,
                'mean_residential_property_price' => 130000,
                'median_residential_property_price' => 108000,
                'value_of_residential_property_sales' => 1170000,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'month' => 'February 2004',
                'local_authority' => 'Aberdeen City',
                'local_authority_code' => 'S12000033',
                'volume_of_residential_property_sales' => 12,
                'mean_residential_property_price' => 135000,
                'median_residential_property_price' => 110000,
                'value_of_residential_property_sales' => 1590000,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'month' => 'March 2004',
                'local_authority' => 'Dundee City',
                'local_authority_code' => 'S12000042',
                'volume_of_residential_property_sales' => 15,
                'mean_residential_property_price' => 140000,
                'median_residential_property_price' => 115000,
                'value_of_residential_property_sales' => 2130000,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'month' => 'Unknown Month',
                'local_authority' => 'Aberdeen City',
                'local_authority_code' => 'S12000033',
                'volume_of_residential_property_sales' => 99,
                'mean_residential_property_price' => 999999,
                'median_residential_property_price' => 999999,
                'value_of_residential_property_sales' => 9999999,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
