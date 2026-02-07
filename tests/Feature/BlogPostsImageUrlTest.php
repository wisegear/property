<?php

namespace Tests\Feature;

use App\Models\BlogPosts;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BlogPostsImageUrlTest extends TestCase
{
    public function test_featured_image_url_uses_public_storage_disk(): void
    {
        Storage::fake('public');

        $post = new BlogPosts;
        $post->original_image = 'example.jpg';

        $url = $post->featuredImageUrl('small');

        $this->assertSame(Storage::disk('public')->url('assets/images/uploads/small_example.jpg'), $url);
    }

    public function test_gallery_image_url_uses_public_storage_disk(): void
    {
        Storage::fake('public');

        $url = BlogPosts::galleryImageUrl('gallery.jpg');

        $this->assertSame(Storage::disk('public')->url('assets/images/uploads/galleries/gallery.jpg'), $url);
    }

    public function test_content_image_url_uses_public_storage_disk_for_assets_path(): void
    {
        Storage::fake('public');

        $url = BlogPosts::contentImageUrl('/assets/images/uploads/inline.jpg');

        $this->assertSame(Storage::disk('public')->url('assets/images/uploads/inline.jpg'), $url);
    }

    public function test_content_image_url_uses_public_storage_disk_for_relative_filename(): void
    {
        Storage::fake('public');

        $url = BlogPosts::contentImageUrl('inline.jpg');

        $this->assertSame(Storage::disk('public')->url('assets/images/uploads/inline.jpg'), $url);
    }
}
