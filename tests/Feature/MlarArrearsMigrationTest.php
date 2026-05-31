<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MlarArrearsMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_mlar_arrears_table_does_not_include_band_column(): void
    {
        $this->assertTrue(Schema::hasTable('mlar_arrears'));
        $this->assertFalse(Schema::hasColumn('mlar_arrears', 'band'));
        $this->assertTrue(Schema::hasColumns('mlar_arrears', [
            'id',
            'description',
            'year',
            'quarter',
            'value',
            'created_at',
            'updated_at',
        ]));
    }
}
