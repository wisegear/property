<?php

namespace Tests\Feature;

use App\Models\ChangeLogEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ChangeLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_index_only_shows_published_entries_newest_first(): void
    {
        $author = User::factory()->create(['name' => 'Research Author']);
        ChangeLogEntry::factory()->for($author, 'author')->create(['title' => 'Older update', 'published_at' => now()->subDays(2)]);
        ChangeLogEntry::factory()->for($author, 'author')->create(['title' => 'Newest update', 'published_at' => now()->subDay()]);
        ChangeLogEntry::factory()->for($author, 'author')->create(['title' => 'Future update', 'published_at' => now()->addDay()]);

        $this->get(route('changelog.index', absolute: false))->assertOk()->assertSeeInOrder(['Newest update', 'Older update'])->assertDontSee('Future update');
    }

    public function test_home_page_shows_only_the_four_latest_published_change_log_entries(): void
    {
        $entries = collect(range(1, 5))->map(fn (int $day) => ChangeLogEntry::factory()->create([
            'title' => "Homepage update {$day}",
            'body' => "Details for homepage update {$day}.",
            'published_at' => now()->subDays($day),
        ]));
        ChangeLogEntry::factory()->create(['title' => 'Scheduled homepage update', 'published_at' => now()->addDay()]);

        $response = $this->get(route('home', absolute: false));

        $response->assertOk()->assertSee('Latest Changes')->assertSee('View change log')
            ->assertSeeInOrder($entries->take(4)->pluck('title')->all())
            ->assertDontSee('Homepage update 5')->assertDontSee('Scheduled homepage update')
            ->assertDontSee('Latest Insights');

        foreach ($entries->take(4) as $entry) {
            $response->assertSee(route('changelog.show', $entry), false)->assertSee($entry->body);
        }
    }

    public function test_home_page_hides_change_log_section_when_no_entries_are_published(): void
    {
        ChangeLogEntry::factory()->create(['title' => 'Scheduled homepage update', 'published_at' => now()->addDay()]);

        $this->get(route('home', absolute: false))->assertOk()
            ->assertDontSee('Latest Changes')->assertDontSee('Scheduled homepage update');
    }

    public function test_public_entry_displays_its_details_and_author(): void
    {
        $entry = ChangeLogEntry::factory()->create(['category' => 'Change', 'title' => 'Search refreshed', 'body' => 'The search interface is now faster.', 'published_at' => now()->subMinute()]);

        $this->get(route('changelog.show', $entry, absolute: false))->assertOk()->assertSee('Search refreshed')->assertSee('The search interface is now faster.')->assertSee($entry->author->name);
    }

    public function test_future_entry_cannot_be_viewed_publicly(): void
    {
        $entry = ChangeLogEntry::factory()->create(['published_at' => now()->addHour()]);

        $this->get(route('changelog.show', $entry, absolute: false))->assertNotFound();
    }

    public function test_admin_can_create_update_and_delete_an_entry(): void
    {
        $admin = $this->createAdmin();
        $publishedAt = now()->seconds(0);

        $this->actingAs($admin)->get(route('admin.changelog.index', absolute: false))->assertOk();
        $this->actingAs($admin)->get(route('admin.changelog.create', absolute: false))->assertOk()->assertSee('Add Change Log Entry');

        $this->actingAs($admin)->post(route('admin.changelog.store', absolute: false), ['category' => 'Update', 'title' => 'Land Registry refreshed', 'body' => 'The latest monthly records are available.', 'published_at' => $publishedAt->format('Y-m-d H:i:s')])->assertRedirect(route('admin.changelog.index', absolute: false));
        $entry = ChangeLogEntry::query()->sole();
        $this->assertSame($admin->id, $entry->author_id);
        $this->actingAs($admin)->get(route('admin.changelog.edit', $entry, absolute: false))->assertOk()->assertSee('Land Registry refreshed');

        $this->actingAs($admin)->put(route('admin.changelog.update', $entry, absolute: false), ['category' => 'Change', 'title' => 'Updated title', 'body' => 'Updated body.', 'published_at' => $publishedAt->format('Y-m-d H:i:s')])->assertRedirect(route('admin.changelog.index', absolute: false));
        $this->assertDatabaseHas('change_log_entries', ['id' => $entry->id, 'category' => 'Change', 'title' => 'Updated title']);

        $this->actingAs($admin)->delete(route('admin.changelog.destroy', $entry, absolute: false))->assertRedirect(route('admin.changelog.index', absolute: false));
        $this->assertDatabaseMissing('change_log_entries', ['id' => $entry->id]);
    }

    public function test_category_validation_only_accepts_change_or_update(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)->post(route('admin.changelog.store', absolute: false), ['category' => 'News', 'title' => 'Invalid entry', 'body' => 'Invalid category.', 'published_at' => now()->format('Y-m-d H:i:s')])->assertSessionHasErrors('category');
    }

    public function test_non_admin_cannot_access_change_log_management(): void
    {
        $this->actingAs(User::factory()->create())->get(route('admin.changelog.index', absolute: false))->assertForbidden();
    }

    public function test_blog_routes_are_unavailable_and_navigation_has_no_blog_links(): void
    {
        foreach (['/blog', '/blog/create', '/blog/market-update', '/blog/1/edit', '/admin/blog', '/admin/blog/create'] as $path) {
            $this->get($path)->assertNotFound();
        }

        foreach (app('router')->getRoutes() as $route) {
            $this->assertFalse(str_contains($route->uri(), 'blog'));
        }

        $this->get('/')->assertOk()->assertDontSee(url('/blog').'"', false);
        $this->actingAs($this->createAdmin())->get('/admin')->assertOk()->assertDontSee(url('/blog').'"', false);
    }

    private function createAdmin(): User
    {
        $admin = User::factory()->create();
        $roleId = DB::table('user_roles')->insertGetId(['name' => 'Admin', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('user_roles_pivot')->insert(['role_id' => $roleId, 'user_id' => $admin->id, 'created_at' => now(), 'updated_at' => now()]);

        return $admin;
    }
}
