<?php

namespace Tests\Feature;

use App\Services\Property\MonthlyPropertySnapshot;
use Carbon\Carbon;
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

        Carbon::setTestNow('2026-08-18 12:00:00');
        Cache::flush();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
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
            ->assertSee('How does this month compare?')
            ->assertSee('The current month may continue to be backfilled')
            ->assertSee('vs May')
            ->assertSee('vs June 2025')
            ->assertSee('£1m+ sales share')
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

    public function test_missing_comparison_months_are_shown_as_unavailable(): void
    {
        DB::table('land_registry')->insert(
            $this->transaction('one', 250000, '2026-06-10', 'T', 'N', 'F', 'A')
        );

        $this->get('/property/monthly-snapshot')
            ->assertOk()
            ->assertSee('—')
            ->assertSee('vs May')
            ->assertSee('vs June 2025');
    }

    public function test_comparison_metrics_use_the_same_category_a_population(): void
    {
        DB::table('land_registry')->insert([
            $this->transaction('year-one', 500000, '2025-06-10', 'D', 'N', 'F', 'A'),
            $this->transaction('previous-one', 250000, '2026-05-10', 'T', 'N', 'F', 'A'),
            $this->transaction('previous-two', 250000, '2026-05-11', 'T', 'N', 'F', 'A'),
            $this->transaction('previous-three', 250000, '2026-05-12', 'T', 'N', 'F', 'A'),
            $this->transaction('previous-four', 1000000, '2026-05-13', 'D', 'N', 'F', 'A'),
            $this->transaction('current-one', 500000, '2026-06-10', 'D', 'N', 'F', 'A'),
            $this->transaction('current-two', 1000000, '2026-06-11', 'D', 'N', 'F', 'A'),
            $this->transaction('excluded', 9000000, '2026-06-12', 'D', 'N', 'F', 'B'),
        ]);

        $comparison = app(MonthlyPropertySnapshot::class)->build(Carbon::parse('2026-06-01'))['comparison'];

        $this->assertSame(-50.0, $comparison['sales']['previous_change']);
        $this->assertSame(100.0, $comparison['sales']['year_change']);
        $this->assertSame(50.0, $comparison['million_plus_share']['current']);
        $this->assertSame(25.0, $comparison['million_plus_share']['previous_change']);
        $this->assertSame(50.0, $comparison['million_plus_share']['year_change']);
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

    public function test_current_year_navigation_shows_available_months_and_marks_the_selected_month(): void
    {
        DB::table('land_registry')->insert([
            $this->transaction('january', 200000, '2026-01-10', 'T', 'N', 'F', 'A'),
            $this->transaction('march', 300000, '2026-03-10', 'T', 'N', 'F', 'A'),
            $this->transaction('june', 400000, '2026-06-10', 'T', 'N', 'F', 'A'),
        ]);

        $this->get('/property/monthly-snapshot/2026/03')
            ->assertOk()
            ->assertSee('2026 snapshots')
            ->assertSee('data-snapshot-month="2026-01"', false)
            ->assertSee('data-snapshot-month="2026-03"', false)
            ->assertSee('data-snapshot-month="2026-06"', false)
            ->assertSee('aria-current="page"', false)
            ->assertDontSee('data-snapshot-month="2026-07"', false);
    }

    public function test_historical_month_changes_all_snapshot_data(): void
    {
        DB::table('land_registry')->insert([
            $this->transaction('march-one', 200000, '2026-03-10', 'T', 'N', 'F', 'A'),
            $this->transaction('march-two', 400000, '2026-03-11', 'D', 'N', 'F', 'A'),
            $this->transaction('june', 900000, '2026-06-10', 'F', 'N', 'L', 'A'),
        ]);

        $this->get('/property/monthly-snapshot/2026/03')
            ->assertOk()
            ->assertSee('March 2026 Property Snapshot')
            ->assertSee('£300,000')
            ->assertSee('vs February')
            ->assertSee('vs March 2025');
    }

    public function test_january_compares_with_previous_december_and_same_month_last_year(): void
    {
        DB::table('land_registry')->insert([
            $this->transaction('year-ago', 200000, '2025-01-10', 'T', 'N', 'F', 'A'),
            $this->transaction('december', 200000, '2025-12-10', 'T', 'N', 'F', 'A'),
            $this->transaction('january-one', 200000, '2026-01-10', 'T', 'N', 'F', 'A'),
            $this->transaction('january-two', 200000, '2026-01-11', 'T', 'N', 'F', 'A'),
        ]);

        $this->get('/property/monthly-snapshot/2026/01')
            ->assertOk()
            ->assertSee('+100.0%')
            ->assertSee('vs December')
            ->assertSee('vs January 2025');
    }

    public function test_invalid_unavailable_and_future_months_return_not_found(): void
    {
        DB::table('land_registry')->insert([
            $this->transaction('january', 200000, '2026-01-10', 'T', 'N', 'F', 'A'),
            $this->transaction('june', 400000, '2026-06-10', 'T', 'N', 'F', 'A'),
        ]);

        $this->get('/property/monthly-snapshot/2026/13')->assertNotFound();
        $this->get('/property/monthly-snapshot/2026/02')->assertNotFound();
        $this->get('/property/monthly-snapshot/2026/07')->assertNotFound();
    }

    public function test_latest_alias_uses_latest_month_and_points_to_its_dated_canonical_url(): void
    {
        DB::table('land_registry')->insert([
            $this->transaction('march', 200000, '2026-03-10', 'T', 'N', 'F', 'A'),
            $this->transaction('june', 400000, '2026-06-10', 'T', 'N', 'F', 'A'),
        ]);

        $canonicalUrl = route('property.monthly-snapshot.show', ['year' => '2026', 'month' => '06']);

        $this->get('/property/monthly-snapshot')
            ->assertOk()
            ->assertSee('June 2026 Property Snapshot')
            ->assertSee('<link rel="canonical" href="'.$canonicalUrl.'">', false);

        $this->get('/property/monthly-snapshot/2026/06')
            ->assertOk()
            ->assertSee('<link rel="canonical" href="'.$canonicalUrl.'">', false);
    }

    public function test_historical_snapshot_reflects_newly_backfilled_database_records(): void
    {
        DB::table('land_registry')->insert(
            $this->transaction('january-one', 200000, '2026-01-10', 'T', 'N', 'F', 'A')
        );

        $this->get('/property/monthly-snapshot/2026/01')
            ->assertOk()
            ->assertSeeInOrder(['>1</span>', 'recorded sales'], false);

        DB::table('land_registry')->insert(
            $this->transaction('january-two', 400000, '2026-01-11', 'D', 'N', 'F', 'A')
        );
        Cache::flush();

        $this->get('/property/monthly-snapshot/2026/01')
            ->assertOk()
            ->assertSeeInOrder(['>2</span>', 'recorded sales'], false)
            ->assertSee('£300,000');
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
