<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProfileAdminControlsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_controls_checkboxes_reflect_user_state(): void
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

        $user = User::factory()->create([
            'trusted' => true,
            'lock' => true,
        ]);
        DB::table('users')->where('id', $user->id)->update([
            'trusted' => true,
            'lock' => true,
        ]);
        $user->refresh();

        $response = $this->actingAs($admin)->get(route('profile.edit', $user->name_slug, absolute: false));

        $response->assertOk();
        $content = $response->getContent();
        $this->assertMatchesRegularExpression('/<input[^>]*id="trusted"[^>]*checked/i', $content);
        $this->assertMatchesRegularExpression('/<input[^>]*id="lock"[^>]*checked/i', $content);
    }
}
