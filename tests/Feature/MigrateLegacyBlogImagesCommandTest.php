<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MigrateLegacyBlogImagesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_command_moves_legacy_files_and_updates_paths(): void
    {
        Storage::fake('public');

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
            'id' => 1,
            'original_image' => 'featured.jpg',
            'title' => 'Legacy Post',
            'slug' => 'legacy-post',
            'date' => now()->toDateString(),
            'summary' => 'Summary',
            'featured' => 0,
            'published' => 1,
            'body' => '<img src="/assets/images/uploads/inline-test.jpg">',
            'images' => json_encode(['/assets/images/uploads/inline-test.jpg']),
            'user_id' => 1,
            'categories_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $legacyBase = public_path('assets/images/uploads');
        File::ensureDirectoryExists($legacyBase);
        File::ensureDirectoryExists($legacyBase.'/galleries');

        File::put($legacyBase.'/inline-test.jpg', 'inline');
        File::put($legacyBase.'/featured.jpg', 'featured');
        File::put($legacyBase.'/small_featured.jpg', 'small');
        File::put($legacyBase.'/galleries/gallery.jpg', 'gallery');

        $this->artisan('blog:images:migrate-legacy', ['--dry-run' => true])
            ->assertExitCode(0);

        $this->assertTrue(File::exists($legacyBase.'/inline-test.jpg'));
        $this->assertSame(['/assets/images/uploads/inline-test.jpg'], json_decode(DB::table('blog_posts')->value('images'), true));

        $this->artisan('blog:images:migrate-legacy')
            ->assertExitCode(0);

        $this->assertFalse(File::exists($legacyBase.'/inline-test.jpg'));
        Storage::disk('public')->assertExists('assets/images/uploads/inline-test.jpg');
        Storage::disk('public')->assertExists('assets/images/uploads/featured.jpg');
        Storage::disk('public')->assertExists('assets/images/uploads/small_featured.jpg');
        Storage::disk('public')->assertExists('assets/images/uploads/galleries/gallery.jpg');

        $post = DB::table('blog_posts')->first();
        $this->assertSame(['/assets/images/uploads/inline-test.jpg'], json_decode($post->images, true));
        $this->assertStringContainsString('/storage/assets/images/uploads/inline-test.jpg', $post->body);
    }
}
