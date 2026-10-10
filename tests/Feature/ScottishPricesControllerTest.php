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
            $table->unsignedInteger('volume')->nullable();
            $table->unsignedInteger('mean_residential_property_price')->nullable();
            $table->decimal('median', 14, 2)->nullable();
            $table->decimal('lower_quartile', 14, 2)->nullable();
            $table->decimal('upper_quartile', 14, 2)->nullable();
            $table->unsignedBigInteger('total_value')->nullable();
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
        $response->assertSee('rounded-sm border border-zinc-200 bg-white p-5 shadow-sm sm:p-6', false);
        $response->assertSee('rounded bg-zinc-900', false);
        $response->assertSee('borderRadius: 0', false);
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

        $this->assertSame(['Aberdeen City', 'Dundee City'], Cache::get('scottish_prices:v2:authorities'));
        $this->assertSame([
            'years' => [2003, 2004],
            'meanPrices' => [110000.0, 135000.0],
            'medianPrices' => [95000.0, 111000.0],
            'lowerQuartilePrices' => [null, null],
            'upperQuartilePrices' => [null, null],
            'salesVolumes' => [30, 36],
            'salesValues' => [3160000.0, 4890000.0],
        ], Cache::get('scottish_prices:v2:scotland'));
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
            'lowerQuartilePrices' => [null, null],
            'upperQuartilePrices' => [null, null],
            'salesVolumes' => [22, 21],
            'salesValues' => [2200000.0, 2760000.0],
        ], Cache::get('scottish_prices:v2:la:'.md5('aberdeen city')));
    }

    public function test_public_api_returns_scottish_prices_and_filters_by_authority(): void
    {
        $this->seedScottishPropertyPrices();

        $this->getJson('/api/v1/property/scottish-prices')
            ->assertOk()
            ->assertJsonPath('data.localAuthorities.0', 'Aberdeen City')
            ->assertJsonPath('data.selectedAuthority', null)
            ->assertJsonPath('data.years.1', 2004)
            ->assertJsonPath('data.stats.latestMeanPrice', 135000);

        $this->getJson('/api/v1/property/scottish-prices?local_authority=Aberdeen%20City')
            ->assertOk()
            ->assertJsonPath('data.selectedAuthority', 'Aberdeen City')
            ->assertJsonPath('data.meanPrices.1', 132500)
            ->assertJsonPath('data.salesVolumes.1', 21);
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

    public function test_latest_month_is_read_from_the_table_instead_of_a_stale_cache_entry(): void
    {
        $this->seedScottishPropertyPrices();
        Cache::put('scottish_prices:v2:latest_month', 'May 2003', now()->addDays(45));

        DB::table('scottish_property_prices')->insert([
            'month' => 'June 2026',
            'local_authority' => 'Aberdeen City',
            'local_authority_code' => 'S12000033',
            'volume' => 10,
            'mean_residential_property_price' => 200000,
            'median' => 190000,
            'total_value' => 2000000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->get(route('property.scottish-prices', absolute: false))
            ->assertOk()
            ->assertViewHas('latestCoveredMonth', 'June 2026')
            ->assertSee('Latest data: June 2026');
    }

    public function test_scotland_view_uses_national_records_without_double_counting_councils(): void
    {
        $this->seedScottishPropertyPrices();
        DB::table('scottish_property_prices')->insert([
            'month' => 'April 2003', 'local_authority' => 'Scotland',
            'local_authority_code' => 'S92000003', 'volume' => 30,
            'total_value' => 3160000, 'mean_residential_property_price' => 105333,
            'median' => 95000.50, 'lower_quartile' => 70000.25,
            'upper_quartile' => 140000.75,
        ]);
        $this->get('/property/scottish-prices')->assertOk()
            ->assertViewHas('localAuthorities', ['Aberdeen City', 'Dundee City'])
            ->assertViewHas('years', [2003])
            ->assertViewHas('salesVolumes', [30])
            ->assertViewHas('salesValues', [3160000.0])
            ->assertViewHas('medianPrices', [95000.50])
            ->assertViewHas('lowerQuartilePrices', [70000.25])
            ->assertViewHas('upperQuartilePrices', [140000.75]);
    }

    private function seedScottishPropertyPrices(): void
    {
        DB::table('scottish_property_prices')->insert([
            [
                'month' => 'April 2003',
                'local_authority' => 'Aberdeen City',
                'local_authority_code' => 'S12000033',
                'volume' => 10,
                'mean_residential_property_price' => 100000,
                'median' => 90000,
                'total_value' => 1000000,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'month' => 'May 2003',
                'local_authority' => 'Aberdeen City',
                'local_authority_code' => 'S12000033',
                'volume' => 12,
                'mean_residential_property_price' => 110000,
                'median' => 95000,
                'total_value' => 1200000,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'month' => 'June 2003',
                'local_authority' => 'Dundee City',
                'local_authority_code' => 'S12000042',
                'volume' => 8,
                'mean_residential_property_price' => 120000,
                'median' => 100000,
                'total_value' => 960000,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'month' => 'January 2004',
                'local_authority' => 'Aberdeen City',
                'local_authority_code' => 'S12000033',
                'volume' => 9,
                'mean_residential_property_price' => 130000,
                'median' => 108000,
                'total_value' => 1170000,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'month' => 'February 2004',
                'local_authority' => 'Aberdeen City',
                'local_authority_code' => 'S12000033',
                'volume' => 12,
                'mean_residential_property_price' => 135000,
                'median' => 110000,
                'total_value' => 1590000,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'month' => 'March 2004',
                'local_authority' => 'Dundee City',
                'local_authority_code' => 'S12000042',
                'volume' => 15,
                'mean_residential_property_price' => 140000,
                'median' => 115000,
                'total_value' => 2130000,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'month' => 'Unknown Month',
                'local_authority' => 'Aberdeen City',
                'local_authority_code' => 'S12000033',
                'volume' => 99,
                'mean_residential_property_price' => 999999,
                'median' => 999999,
                'total_value' => 9999999,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
