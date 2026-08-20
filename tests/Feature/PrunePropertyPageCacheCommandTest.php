<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PrunePropertyPageCacheCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_deletes_only_obsolete_per_property_cache_entries(): void
    {
        $prefix = (string) config('cache.prefix');
        $expiration = now()->addDays(45)->getTimestamp();

        DB::table('cache')->insert([
            ['key' => $prefix.'property:AB1 2CD:10:HIGH STREET:NOSAON:records:v2:catAB', 'value' => 'records', 'expiration' => $expiration],
            ['key' => $prefix.'property:AB1 2CD:10:HIGH STREET:NOSAON:priceHistory:v4:catA', 'value' => 'history', 'expiration' => $expiration],
            ['key' => $prefix.'property:AB1 2CD:10:HIGH STREET:NOSAON:council-tax-estimate:v2', 'value' => 'tax', 'expiration' => $expiration],
            ['key' => $prefix.'property:street:v4:high-street:ab1', 'value' => 'street', 'expiration' => $expiration],
            ['key' => $prefix.'property:monthly-snapshot:v3:202608', 'value' => 'snapshot', 'expiration' => $expiration],
            ['key' => $prefix.'postcode:AB1 2CD:type:D:priceHistory:v4:catA', 'value' => 'postcode', 'expiration' => $expiration],
        ]);

        $this->artisan('cache:prune-property-pages', ['--batch' => 1])
            ->expectsOutput('Pruned 3 obsolete per-property cache entries.')
            ->assertSuccessful();

        $this->assertDatabaseCount('cache', 3);
        $this->assertDatabaseHas('cache', ['key' => $prefix.'property:street:v4:high-street:ab1']);
        $this->assertDatabaseHas('cache', ['key' => $prefix.'property:monthly-snapshot:v3:202608']);
        $this->assertDatabaseHas('cache', ['key' => $prefix.'postcode:AB1 2CD:type:D:priceHistory:v4:catA']);
    }
}
