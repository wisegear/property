<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BlogPostTagsSequenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_blog_post_tags_inserts_without_id(): void
    {
        if (! Schema::hasTable('blog_post_tags')) {
            Schema::create('blog_post_tags', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tag_id');
                $table->unsignedBigInteger('post_id');
            });
        }

        if (Schema::hasTable('users')) {
            DB::table('users')->insert([
                'id' => 1,
                'name' => 'Tester',
                'name_slug' => 'tester',
                'email' => 'tester@example.com',
                'password' => bcrypt('secret'),
            ]);
        }

        if (Schema::hasTable('blog_categories')) {
            DB::table('blog_categories')->insert([
                'id' => 1,
                'name' => 'General',
            ]);
        }

        if (Schema::hasTable('blog_posts')) {
            foreach ([
                'title' => fn (Blueprint $table) => $table->string('title', 150),
                'slug' => fn (Blueprint $table) => $table->string('slug', 200),
                'date' => fn (Blueprint $table) => $table->date('date')->nullable(),
                'summary' => fn (Blueprint $table) => $table->text('summary'),
                'featured' => fn (Blueprint $table) => $table->boolean('featured')->default(false),
                'published' => fn (Blueprint $table) => $table->boolean('published')->default(true),
                'body' => fn (Blueprint $table) => $table->text('body'),
                'user_id' => fn (Blueprint $table) => $table->unsignedBigInteger('user_id'),
                'categories_id' => fn (Blueprint $table) => $table->unsignedBigInteger('categories_id'),
                'created_at' => fn (Blueprint $table) => $table->timestamp('created_at')->nullable(),
                'updated_at' => fn (Blueprint $table) => $table->timestamp('updated_at')->nullable(),
            ] as $column => $definition) {
                if (! Schema::hasColumn('blog_posts', $column)) {
                    Schema::table('blog_posts', function (Blueprint $table) use ($definition): void {
                        $definition($table);
                    });
                }
            }

            DB::table('blog_posts')->insert([
                'id' => 1,
                'title' => 'Test Post',
                'slug' => 'test-post',
                'date' => now()->toDateString(),
                'summary' => 'Summary',
                'featured' => 0,
                'published' => 1,
                'body' => 'Body',
                'user_id' => 1,
                'categories_id' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (Schema::hasTable('blog_tags')) {
            DB::table('blog_tags')->insert([
                'id' => 1,
                'name' => 'Tag 1',
            ]);

            DB::table('blog_tags')->insert([
                'id' => 2,
                'name' => 'Tag 2',
            ]);
        }

        DB::table('blog_post_tags')->insert([
            'tag_id' => 1,
            'post_id' => 1,
        ]);

        DB::table('blog_post_tags')->insert([
            'tag_id' => 2,
            'post_id' => 1,
        ]);

        $this->assertSame(2, DB::table('blog_post_tags')->count());
    }
}
