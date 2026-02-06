<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CheckboxValueTest extends TestCase
{
    use RefreshDatabase;

    public function test_blog_create_checkboxes_have_value_one(): void
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

        $response = $this->actingAs($admin)->get(route('blog.create', absolute: false));

        $response->assertOk();
        $response->assertSee('id="published" name="published" value="1"', false);
        $response->assertSee('id="featured" name="featured" value="1"', false);
    }

    public function test_stamp_duty_checkboxes_have_value_one(): void
    {
        $response = $this->get('/stamp-duty');

        $response->assertOk();
        $response->assertSee('id="additional_property" name="additional_property" value="1"', false);
        $response->assertSee('id="non_resident" name="non_resident" value="1"', false);
    }
}
