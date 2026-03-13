<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('market_insights') || Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE market_insights DROP CONSTRAINT IF EXISTS market_insights_insight_type_check');
        DB::statement(<<<'SQL'
ALTER TABLE market_insights
ADD CONSTRAINT market_insights_insight_type_check
CHECK (insight_type IN (
    'price_spike',
    'price_collapse',
    'demand_collapse',
    'liquidity_stress',
    'liquidity_surge',
    'market_freeze',
    'sector_outperformance',
    'momentum_reversal',
    'unexpected_hotspot'
))
SQL);
    }

    public function down(): void
    {
        if (! Schema::hasTable('market_insights') || Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE market_insights DROP CONSTRAINT IF EXISTS market_insights_insight_type_check');
        DB::statement(<<<'SQL'
ALTER TABLE market_insights
ADD CONSTRAINT market_insights_insight_type_check
CHECK (insight_type IN (
    'price_spike',
    'price_collapse',
    'demand_collapse',
    'liquidity_surge',
    'market_freeze',
    'sector_outperformance',
    'momentum_reversal',
    'unexpected_hotspot'
))
SQL);
    }
};
