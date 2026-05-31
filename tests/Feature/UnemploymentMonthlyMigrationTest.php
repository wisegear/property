<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UnemploymentMonthlyMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unemployment_monthly_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('unemployment_monthly'));
        $this->assertFalse(Schema::hasColumn('unemployment_monthly', 'rate'));
        $this->assertTrue(Schema::hasColumns('unemployment_monthly', [
            'id',
            'date',
            'single_month',
            'single',
            'three_month',
            'created_at',
            'updated_at',
        ]));
    }
}
