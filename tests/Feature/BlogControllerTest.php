<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BlogControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureBlogTablesExist();
    }

    public function test_blog_index_renders_posts_and_popular_tags(): void
    {
        $this->seedBlogFixtures();

        $response = $this->get(route('blog.index', absolute: false));

        $response->assertOk();
        $response->assertSee('Market notes, data checks');
        $response->assertSee('Market Update');
        $response->assertSee('Housing');
    }

    public function test_blog_search_filters_posts_with_case_insensitive_matching(): void
    {
        $this->seedBlogFixtures();

        $response = $this->get(route('blog.index', ['search' => 'market'], absolute: false));

        $response->assertOk();
        $posts = $response->viewData('posts');

        $this->assertSame(2, $posts->count());
        $this->assertTrue($posts->contains(fn ($post) => $post->slug === 'market-update'));
        $this->assertFalse($posts->contains(fn ($post) => $post->slug === 'rental-deep-dive'));
    }

    public function test_blog_show_renders_requested_slug(): void
    {
        $this->seedBlogFixtures();

        $response = $this->get(route('blog.show', ['blog' => 'market-update'], absolute: false));

        $response->assertOk();
        $response->assertSee('Market Update');
        $this->assertSame('market-update', $response->viewData('page')->slug);
    }

    public function test_blog_category_filter_accepts_lowercase_slug_values(): void
    {
        $this->seedBlogFixtures();

        $response = $this->get(route('blog.index', ['category' => 'source'], absolute: false));

        $response->assertOk();
        $posts = $response->viewData('posts');

        $this->assertSame(1, $posts->count());
        $this->assertSame('source-notes', $posts->first()->slug);
    }

    protected function seedBlogFixtures(): void
    {
        DB::table('blog_post_tags')->delete();
        DB::table('blog_posts')->delete();
        DB::table('blog_tags')->delete();
        DB::table('blog_categories')->delete();
        DB::table('comments')->delete();
        DB::table('users')->delete();

        DB::table('users')->insert([
            'id' => 1,
            'name' => 'Lee',
            'email' => 'lee@example.com',
            'password' => bcrypt('password'),
            'avatar' => 'avatar.jpg',
            'bio' => 'Research analyst.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('blog_categories')->insert([
            'id' => 1,
            'name' => 'Research',
        ]);

        DB::table('blog_categories')->insert([
            'id' => 2,
            'name' => 'Source',
        ]);

        DB::table('blog_tags')->insert([
            'id' => 1,
            'name' => 'Housing',
        ]);

        DB::table('blog_posts')->insert([
            [
                'id' => 1,
                'original_image' => 'one.jpg',
                'title' => 'Older Trends',
                'slug' => 'older-trends',
                'date' => '2025-01-10',
                'summary' => 'Older summary.',
                'featured' => false,
                'published' => true,
                'body' => 'Historical market view.',
                'images' => json_encode([]),
                'user_id' => 1,
                'categories_id' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'original_image' => 'two.jpg',
                'title' => 'Market Update',
                'slug' => 'market-update',
                'date' => '2025-02-10',
                'summary' => 'Current summary.',
                'featured' => true,
                'published' => true,
                'body' => 'Latest MARKET numbers.',
                'images' => json_encode([]),
                'user_id' => 1,
                'categories_id' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 3,
                'original_image' => 'three.jpg',
                'title' => 'Rental Deep Dive',
                'slug' => 'rental-deep-dive',
                'date' => '2025-03-10',
                'summary' => 'Rental summary.',
                'featured' => false,
                'published' => true,
                'body' => 'Rental and affordability insights.',
                'images' => json_encode([]),
                'user_id' => 1,
                'categories_id' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 4,
                'original_image' => 'four.jpg',
                'title' => 'Source Notes',
                'slug' => 'source-notes',
                'date' => '2025-04-10',
                'summary' => 'Source summary.',
                'featured' => false,
                'published' => true,
                'body' => 'Source material references.',
                'images' => json_encode([]),
                'user_id' => 1,
                'categories_id' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('blog_post_tags')->insert([
            ['tag_id' => 1, 'post_id' => 1],
            ['tag_id' => 1, 'post_id' => 2],
        ]);
    }

    protected function ensureBlogTablesExist(): void
    {
        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->string('password');
                $table->string('avatar')->nullable();
                $table->text('bio')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('blog_categories')) {
            Schema::create('blog_categories', function (Blueprint $table) {
                $table->id();
                $table->string('name', 150);
            });
        }

        if (! Schema::hasTable('blog_posts')) {
            Schema::create('blog_posts', function (Blueprint $table) {
                $table->id();
                $table->text('original_image')->nullable();
                $table->string('title', 150);
                $table->string('slug', 200);
                $table->date('date')->nullable();
                $table->text('summary')->nullable();
                $table->boolean('featured')->default(false);
                $table->boolean('published')->default(true);
                $table->text('body');
                $table->json('images')->nullable();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('categories_id');
                $table->json('gallery_images')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('blog_tags')) {
            Schema::create('blog_tags', function (Blueprint $table) {
                $table->id();
                $table->string('name', 50);
            });
        }

        if (! Schema::hasTable('blog_post_tags')) {
            Schema::create('blog_post_tags', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tag_id');
                $table->unsignedBigInteger('post_id');
            });
        }

        if (! Schema::hasTable('comments')) {
            Schema::create('comments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('commentable_type');
                $table->unsignedBigInteger('commentable_id');
                $table->text('body')->nullable();
                $table->timestamps();
            });
        }
    }
}
