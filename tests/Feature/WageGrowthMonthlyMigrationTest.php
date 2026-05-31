<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WageGrowthMonthlyMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_wage_growth_monthly_table_does_not_include_single_month_yoy(): void
    {
        $this->assertTrue(Schema::hasTable('wage_growth_monthly'));
        $this->assertFalse(Schema::hasColumn('wage_growth_monthly', 'single_month_yoy'));
        $this->assertSame(
            ['id', 'date', 'three_month_avg_yoy', 'created_at', 'updated_at'],
            Schema::getColumnListing('wage_growth_monthly')
        );
    }
}
