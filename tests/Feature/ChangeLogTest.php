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

    private function createAdmin(): User
    {
        $admin = User::factory()->create();
        $roleId = DB::table('user_roles')->insertGetId(['name' => 'Admin', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('user_roles_pivot')->insert(['role_id' => $roleId, 'user_id' => $admin->id, 'created_at' => now(), 'updated_at' => now()]);

        return $admin;
    }
}
