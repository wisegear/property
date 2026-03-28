<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CrimeMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_crime_table_has_expected_columns_and_indexes(): void
    {
        $this->assertTrue(Schema::hasTable('crime'));

        $this->assertTrue(Schema::hasColumns('crime', [
            'id',
            'crime_id',
            'month',
            'reported_by',
            'falls_within',
            'longitude',
            'latitude',
            'location',
            'lsoa_code',
            'lsoa_name',
            'crime_type',
            'last_outcome_category',
            'context',
        ]));

        $this->assertIndexExists('crime_month_index', ['month']);
        $this->assertIndexExists('crime_latitude_longitude_index', ['latitude', 'longitude']);
        $this->assertIndexExists('crime_crime_type_index', ['crime_type']);
    }

    protected function assertIndexExists(string $indexName, array $expectedColumns): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $indexes = collect(DB::select("PRAGMA index_list('crime')"))->keyBy('name');

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
              AND tablename = 'crime'
        "))->keyBy('indexname');

        $this->assertArrayHasKey($indexName, $indexes->all());
        $this->assertStringContainsString(
            '('.implode(', ', $expectedColumns).')',
            $indexes[$indexName]->indexdef
        );
    }
}
