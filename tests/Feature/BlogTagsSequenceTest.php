<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BlogTagsSequenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_blog_tags_sequence_is_reset_after_manual_misalignment(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Sequence reset applies to PostgreSQL.');
        }

        DB::table('blog_tags')->insert([
            'id' => 1,
            'name' => 'existing',
        ]);

        $migration = require base_path('database/migrations/2026_02_06_203847_fix_blog_tags_id_sequence.php');

        $migration->up();

        $sequence = DB::selectOne("SELECT pg_get_serial_sequence('blog_tags', 'id') as seq");
        $sequenceName = $sequence?->seq;

        $this->assertNotNull($sequenceName);

        DB::statement("SELECT setval('{$sequenceName}', 1, false)");

        $migration->up();

        $newId = DB::table('blog_tags')->insertGetId([
            'name' => 'new-tag',
        ]);

        $this->assertGreaterThan(1, $newId);
    }
}
