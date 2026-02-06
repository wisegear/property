<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BlogPostsSequenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_blog_posts_sequence_is_reset_after_manual_misalignment(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Sequence reset applies to PostgreSQL.');
        }

        DB::table('users')->insert([
            'id' => 1,
            'name' => 'Tester',
            'name_slug' => 'tester',
            'email' => 'tester@example.com',
            'password' => bcrypt('secret'),
        ]);

        DB::table('blog_categories')->insert([
            'id' => 1,
            'name' => 'General',
        ]);

        DB::table('blog_posts')->insert([
            'id' => 2,
            'original_image' => 'example.jpg',
            'title' => 'First Post',
            'slug' => 'first-post',
            'date' => now()->toDateString(),
            'summary' => 'Summary',
            'featured' => 0,
            'published' => 1,
            'body' => 'Body',
            'images' => json_encode([]),
            'user_id' => 1,
            'categories_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = require base_path('database/migrations/2026_02_06_203519_fix_blog_posts_id_sequence.php');

        $migration->up();

        $sequence = DB::selectOne("SELECT pg_get_serial_sequence('blog_posts', 'id') as seq");
        $sequenceName = $sequence?->seq;

        $this->assertNotNull($sequenceName);

        DB::statement("SELECT setval('{$sequenceName}', 1, false)");

        $migration->up();

        $newId = DB::table('blog_posts')->insertGetId([
            'original_image' => 'example-two.jpg',
            'title' => 'Second Post',
            'slug' => 'second-post',
            'date' => now()->toDateString(),
            'summary' => 'Summary 2',
            'featured' => 0,
            'published' => 1,
            'body' => 'Body 2',
            'images' => json_encode([]),
            'user_id' => 1,
            'categories_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertGreaterThan(2, $newId);
    }
}
