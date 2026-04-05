<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportTinyMceLoadingTest extends TestCase
{
    use RefreshDatabase;

    public function test_support_index_does_not_load_tinymce(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/support');

        $response->assertOk();
        $response->assertDontSee('https://cdn.jsdelivr.net/npm/tinymce@6/tinymce.min.js', false);
    }

    public function test_support_create_loads_tinymce(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/support/create');

        $response->assertOk();
        $response->assertSee('https://cdn.jsdelivr.net/npm/tinymce@6/tinymce.min.js', false);
    }
}
