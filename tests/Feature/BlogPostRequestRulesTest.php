<?php

namespace Tests\Feature;

use App\Http\Requests\BlogPostStoreRequest;
use App\Http\Requests\BlogPostUpdateRequest;
use Tests\TestCase;

class BlogPostRequestRulesTest extends TestCase
{
    public function test_blog_post_store_request_has_image_rules(): void
    {
        $request = new BlogPostStoreRequest;
        $rules = $request->rules();

        $this->assertArrayHasKey('image', $rules);
        $this->assertContains('required', $rules['image']);
        $this->assertContains('image', $rules['image']);
        $this->assertArrayHasKey('published', $rules);
        $this->assertArrayHasKey('featured', $rules);
    }

    public function test_blog_post_update_request_has_optional_image_rules(): void
    {
        $request = new BlogPostUpdateRequest;
        $rules = $request->rules();

        $this->assertArrayHasKey('image', $rules);
        $this->assertContains('nullable', $rules['image']);
        $this->assertContains('image', $rules['image']);
        $this->assertArrayHasKey('published', $rules);
        $this->assertArrayHasKey('featured', $rules);
    }
}
