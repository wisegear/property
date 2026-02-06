<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProfileAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_edit_and_update_profile_without_member_role(): void
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

        $response = $this->actingAs($admin)->get(route('profile.edit', $admin->name_slug, absolute: false));
        $response->assertOk();

        $updateResponse = $this->actingAs($admin)->put(route('profile.update', $admin->name_slug, absolute: false), [
            'email' => 'admin@example.com',
        ]);

        $updateResponse->assertSessionHasNoErrors();
    }
}
