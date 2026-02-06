<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProfileEmailVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_hides_email_when_email_visible_is_false(): void
    {
        $userId = DB::table('users')->insertGetId([
            'name' => 'Hidden User',
            'name_slug' => 'hidden-user',
            'email' => 'hidden@example.com',
            'password' => bcrypt('secret'),
            'email_visible' => false,
        ]);

        DB::table('blog_categories')->insert([
            'id' => 1,
            'name' => 'Research',
        ]);

        DB::table('blog_posts')->insert([
            'id' => 1,
            'original_image' => 'one.jpg',
            'title' => 'Post',
            'slug' => 'post',
            'date' => now()->toDateString(),
            'summary' => 'Summary',
            'featured' => 0,
            'published' => 1,
            'body' => 'Body',
            'images' => json_encode([]),
            'user_id' => $userId,
            'categories_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = \App\Models\User::query()->findOrFail($userId);

        $response = $this->actingAs($user)->get(route('profile.show', 'hidden-user', absolute: false));

        $response->assertOk();
        $response->assertSee('Email Not shared');
        $response->assertDontSee('hidden@example.com');
    }
}
