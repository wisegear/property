<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ScottishPropertyPricesMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_scottish_property_prices_table_has_expected_columns_and_indexes(): void
    {
        $this->assertTrue(Schema::hasTable('scottish_property_prices'));

        $this->assertTrue(Schema::hasColumns('scottish_property_prices', [
            'id',
            'month',
            'local_authority',
            'local_authority_code',
            'median',
            'lower_quartile',
            'upper_quartile',
            'mean_residential_property_price',
            'volume',
            'total_value',
            'created_at',
            'updated_at',
        ]));

        $this->assertIndexExists(
            'scottish_property_prices_local_authority_index',
            ['local_authority']
        );
        $this->assertIndexExists(
            'scottish_property_prices_local_authority_code_index',
            ['local_authority_code']
        );
        $this->assertIndexExists(
            'scottish_property_prices_month_local_authority_code_unique',
            ['month', 'local_authority_code']
        );
    }

    public function test_revised_migration_preserves_existing_records_and_can_be_rolled_back(): void
    {
        $migration = require database_path('migrations/2026_10_10_124230_update_scottish_property_prices_for_revised_ros_fields.php');
        $migration->down();
        DB::table('scottish_property_prices')->insert([
            'month' => 'April 2003', 'local_authority' => 'Aberdeen City',
            'local_authority_code' => 'S12000033',
            'median_residential_property_price' => 51000,
            'mean_residential_property_price' => 71967,
            'volume_of_residential_property_sales' => 521,
            'value_of_residential_property_sales' => 37494560,
        ]);
        $migration->up();
        $this->assertDatabaseHas('scottish_property_prices', [
            'month' => 'April 2003', 'median' => 51000,
            'volume' => 521, 'total_value' => 37494560,
            'mean_residential_property_price' => 71967,
            'lower_quartile' => null, 'upper_quartile' => null,
        ]);
        $migration->down();
        $this->assertDatabaseHas('scottish_property_prices', ['median_residential_property_price' => 51000]);
        $migration->up();
    }

    protected function assertIndexExists(string $indexName, array $expectedColumns): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $indexes = collect(DB::select("PRAGMA index_list('scottish_property_prices')"))->keyBy('name');

            $this->assertArrayHasKey($indexName, $indexes->all());

            $columns = collect(DB::select("PRAGMA index_info('{$indexName}')"))
                ->pluck('name')
                ->all();

            $this->assertSame($expectedColumns, $columns);

            return;
        }

        $indexes = collect(DB::select("
            SELECT indexname, indexdef
            FROM pg_indexes
            WHERE schemaname = current_schema()
              AND tablename = 'scottish_property_prices'
        "))->keyBy('indexname');

        $this->assertArrayHasKey($indexName, $indexes->all());
        $this->assertStringContainsString(
            '('.implode(', ', $expectedColumns).')',
            $indexes[$indexName]->indexdef
        );
    }
}
