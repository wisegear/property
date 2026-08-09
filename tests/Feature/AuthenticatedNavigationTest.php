<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuthenticatedNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_navigation_renders_the_account_dropdown(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/');

        $response->assertOk();
        $response->assertSee('id="userMenuButton"', false);
        $response->assertSee('id="userDropdown"', false);
        $response->assertSee('/profile/'.$user->name_slug, false);
        $response->assertSee('/support', false);
        $response->assertSee('Logout');
    }

    public function test_admin_account_dropdown_includes_the_admin_link(): void
    {
        $admin = User::factory()->create();
        $roleId = DB::table('user_roles')->insertGetId([
            'name' => 'Admin',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('user_roles_pivot')->insert([
            'role_id' => $roleId,
            'user_id' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get('/');

        $response->assertOk();
        $response->assertSee('href="/admin"', false);
    }
}
