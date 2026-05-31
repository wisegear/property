<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MortgageApprovalsMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_mortgage_approvals_unit_defaults_to_count(): void
    {
        DB::table('mortgage_approvals')->insert([
            'series_code' => 'LPMVTVX',
            'period' => '2026-03-01',
            'value' => 63531,
            'source' => 'BoE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $row = DB::table('mortgage_approvals')
            ->where('series_code', 'LPMVTVX')
            ->where('period', '2026-03-01')
            ->first();

        $this->assertSame('count', $row->unit);
    }
}
