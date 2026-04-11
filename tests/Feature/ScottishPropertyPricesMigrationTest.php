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
            'median_residential_property_price',
            'mean_residential_property_price',
            'volume_of_residential_property_sales',
            'value_of_residential_property_sales',
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
