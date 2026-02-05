<?php

namespace Tests\Feature;

use App\Models\RentalCost;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RentalControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureRentalCostsTableExists();
    }

    public function test_rental_index_renders_with_mixed_time_period_formats(): void
    {
        RentalCost::query()->create([
            'time_period' => '2023-12',
            'area_name' => 'United Kingdom',
            'monthly_change' => 0.4,
            'rental_price' => 1200,
        ]);

        RentalCost::query()->create([
            'time_period' => 'Jan-2024',
            'area_name' => 'United Kingdom',
            'monthly_change' => 0.6,
            'rental_price' => 1210,
        ]);

        RentalCost::query()->create([
            'time_period' => '2024-04-01',
            'area_name' => 'United Kingdom',
            'monthly_change' => 0.2,
            'rental_price' => 1230,
        ]);

        $response = $this->get(route('rental.index', absolute: false));

        $response->assertOk();

        $seriesByArea = collect($response->viewData('seriesByArea'));
        $ukSeries = $seriesByArea->firstWhere('name', 'United Kingdom');

        $this->assertSame(['2023-Q4', '2024-Q1', '2024-Q2'], $ukSeries['labels']);
        $this->assertSame('Apr 2024', $response->viewData('latestPeriod'));
    }

    public function test_england_page_builds_type_series_with_portable_date_sorting(): void
    {
        RentalCost::query()->create([
            'time_period' => '2024-02',
            'area_name' => 'England',
            'monthly_change' => 0.3,
            'rental_price' => 1300,
            'monthly_change_one_bed' => 0.7,
            'rental_price_one_bed' => 1100,
        ]);

        RentalCost::query()->create([
            'time_period' => 'Apr-2024',
            'area_name' => 'England',
            'monthly_change' => 0.5,
            'rental_price' => 1320,
            'monthly_change_one_bed' => 0.8,
            'rental_price_one_bed' => 1200,
        ]);

        $response = $this->get(route('rental.england', absolute: false));

        $response->assertOk();

        $typeSeries = collect($response->viewData('typeSeries'));
        $oneBedSeries = $typeSeries->firstWhere('key', 'one_bed');

        $this->assertSame(['2024-Q1', '2024-Q2'], $oneBedSeries['labels']);
        $this->assertSame([1100.0, 1200.0], $oneBedSeries['prices']);
    }

    protected function ensureRentalCostsTableExists(): void
    {
        if (! Schema::hasTable('rental_costs')) {
            Schema::create('rental_costs', function (Blueprint $table) {
                $table->id();
                $table->string('time_period')->nullable();
                $table->string('area_name')->nullable();
                $table->decimal('monthly_change', 12, 4)->nullable();
                $table->decimal('rental_price', 12, 4)->nullable();
                $table->decimal('monthly_change_one_bed', 12, 4)->nullable();
                $table->decimal('rental_price_one_bed', 12, 4)->nullable();
                $table->decimal('monthly_change_two_bed', 12, 4)->nullable();
                $table->decimal('rental_price_two_bed', 12, 4)->nullable();
                $table->decimal('monthly_change_three_bed', 12, 4)->nullable();
                $table->decimal('rental_price_three_bed', 12, 4)->nullable();
                $table->decimal('monthly_change_four_or_more_bed', 12, 4)->nullable();
                $table->decimal('rental_price_four_or_more_bed', 12, 4)->nullable();
                $table->decimal('monthly_change_detached', 12, 4)->nullable();
                $table->decimal('rental_price_detached', 12, 4)->nullable();
                $table->decimal('monthly_change_semidetached', 12, 4)->nullable();
                $table->decimal('rental_price_semidetached', 12, 4)->nullable();
                $table->decimal('monthly_change_terraced', 12, 4)->nullable();
                $table->decimal('rental_price_terraced', 12, 4)->nullable();
                $table->decimal('monthly_change_flat_maisonette', 12, 4)->nullable();
                $table->decimal('rental_price_flat_maisonette', 12, 4)->nullable();
                $table->timestamps();
            });
        }
    }
}
