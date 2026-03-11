<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MarketInsightsMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_market_insights_table_has_expected_columns_and_indexes(): void
    {
        $this->assertTrue(Schema::hasTable('market_insights'));

        $this->assertTrue(Schema::hasColumns('market_insights', [
            'id',
            'area_type',
            'area_code',
            'insight_type',
            'metric_value',
            'transactions',
            'period_start',
            'period_end',
            'supporting_data',
            'insight_text',
            'created_at',
            'updated_at',
        ]));

        $this->assertIndexExists('market_insights_area_type_area_code_index', ['area_type', 'area_code']);
        $this->assertIndexExists('market_insights_insight_type_index', ['insight_type']);
        $this->assertIndexExists('market_insights_period_end_index', ['period_end']);
    }

    protected function assertIndexExists(string $indexName, array $expectedColumns): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $indexes = collect(DB::select("PRAGMA index_list('market_insights')"))->keyBy('name');

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
              AND tablename = 'market_insights'
        "))->keyBy('indexname');

        $this->assertArrayHasKey($indexName, $indexes->all());
        $this->assertStringContainsString(
            '('.implode(', ', $expectedColumns).')',
            $indexes[$indexName]->indexdef
        );
    }
}
