<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CommentsSequenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_comments_sequence_is_reset_after_manual_misalignment(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Sequence reset applies to PostgreSQL.');
        }

        DB::table('comments')->insert([
            'id' => 1,
            'user_id' => 1,
            'commentable_type' => 'App\\Models\\BlogPosts',
            'commentable_id' => 1,
            'body' => 'Existing',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = require base_path('database/migrations/2026_02_06_213520_fix_comments_id_sequence.php');

        $migration->up();

        $sequence = DB::selectOne("SELECT pg_get_serial_sequence('comments', 'id') as seq");
        $sequenceName = $sequence?->seq;

        $this->assertNotNull($sequenceName);

        DB::statement("SELECT setval('{$sequenceName}', 1, false)");

        $migration->up();

        $newId = DB::table('comments')->insertGetId([
            'user_id' => 1,
            'commentable_type' => 'App\\Models\\BlogPosts',
            'commentable_id' => 1,
            'body' => 'New',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertGreaterThan(1, $newId);
    }
}
