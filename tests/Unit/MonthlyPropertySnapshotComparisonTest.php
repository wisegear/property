<?php

namespace Tests\Unit;

use App\Services\Property\MonthlyPropertySnapshot;
use Carbon\Carbon;
use Tests\TestCase;

class MonthlyPropertySnapshotComparisonTest extends TestCase
{
    private MonthlyPropertySnapshot $snapshot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->snapshot = app(MonthlyPropertySnapshot::class);
    }

    public function test_previous_month_crosses_from_january_into_december(): void
    {
        $months = $this->snapshot->comparisonMonths(Carbon::parse('2026-01-15'));

        $this->assertSame('2025-12-01', $months['previous']->toDateString());
    }

    public function test_year_ago_uses_the_same_calendar_month(): void
    {
        $months = $this->snapshot->comparisonMonths(Carbon::parse('2026-06-15'));

        $this->assertSame('2025-06-01', $months['year_ago']->toDateString());
    }

    public function test_percentage_change_is_rounded_to_one_decimal_place(): void
    {
        $this->assertSame(8.2, $this->snapshot->percentageChange(1082, 1000));
        $this->assertSame(-4.7, $this->snapshot->percentageChange(953, 1000));
    }

    public function test_percentage_point_change_is_used_for_sales_share(): void
    {
        $this->assertSame(-0.2, $this->snapshot->percentagePointChange(1.4, 1.6));
    }

    public function test_zero_or_missing_comparison_values_return_null(): void
    {
        $this->assertNull($this->snapshot->percentageChange(100, 0));
        $this->assertNull($this->snapshot->percentageChange(100, null));
        $this->assertNull($this->snapshot->percentagePointChange(1.4, null));
    }
}
