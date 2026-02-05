<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DropLoadedAtFromEpcCertificatesMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_loaded_at_column_is_removed_from_epc_certificates_table(): void
    {
        $this->assertTrue(Schema::hasTable('epc_certificates'));
        $this->assertFalse(Schema::hasColumn('epc_certificates', 'loaded_at'));
    }
}
