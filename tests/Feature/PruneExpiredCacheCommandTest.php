<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PruneExpiredCacheCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_deletes_only_expired_cache_entries_and_locks(): void
    {
        $now = now()->getTimestamp();

        DB::table('cache')->insert([
            ['key' => 'expired-one', 'value' => 'old', 'expiration' => $now - 1],
            ['key' => 'expired-two', 'value' => 'old', 'expiration' => $now],
            ['key' => 'active', 'value' => 'current', 'expiration' => $now + 3600],
        ]);
        DB::table('cache_locks')->insert([
            ['key' => 'expired-lock', 'owner' => 'old', 'expiration' => $now - 1],
            ['key' => 'active-lock', 'owner' => 'current', 'expiration' => $now + 3600],
        ]);

        $this->artisan('cache:prune-expired', ['--batch' => 1])
            ->expectsOutput('Pruned 2 expired cache entries and 1 expired cache locks.')
            ->assertSuccessful();

        $this->assertDatabaseMissing('cache', ['key' => 'expired-one']);
        $this->assertDatabaseMissing('cache', ['key' => 'expired-two']);
        $this->assertDatabaseHas('cache', ['key' => 'active']);
        $this->assertDatabaseMissing('cache_locks', ['key' => 'expired-lock']);
        $this->assertDatabaseHas('cache_locks', ['key' => 'active-lock']);
    }
}
