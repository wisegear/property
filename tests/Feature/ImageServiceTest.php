<?php

namespace Tests\Feature;

use App\Services\ImageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ImageServiceTest extends TestCase
{
    public function test_handle_image_upload_stores_featured_sizes_in_public_disk(): void
    {
        Storage::fake('public');

        $image = UploadedFile::fake()->image('featured.jpg', 1600, 900);
        $imageService = app(ImageService::class);

        $filename = $imageService->handleImageUpload($image);

        Storage::disk('public')->assertExists("assets/images/uploads/{$filename}");
        Storage::disk('public')->assertExists("assets/images/uploads/small_{$filename}");
        Storage::disk('public')->assertExists("assets/images/uploads/medium_{$filename}");
        Storage::disk('public')->assertExists("assets/images/uploads/large_{$filename}");
    }

    public function test_optimize_and_save_image_returns_inline_storage_path(): void
    {
        Storage::fake('public');

        $image = UploadedFile::fake()->image('inline.jpg', 1200, 800);
        $imageService = app(ImageService::class);

        $path = $imageService->optimizeAndSaveImage($image);

        $this->assertTrue(Str::startsWith($path, 'assets/images/uploads/'));
        Storage::disk('public')->assertExists($path);
    }

    public function test_handle_gallery_image_upload_stores_original_and_thumbnail(): void
    {
        Storage::fake('public');

        $image = UploadedFile::fake()->image('gallery.jpg', 1600, 900);
        $imageService = app(ImageService::class);

        $result = $imageService->handleGalleryImageUpload($image);

        $this->assertArrayHasKey('original', $result);
        $this->assertArrayHasKey('thumbnail', $result);
        Storage::disk('public')->assertExists('assets/images/uploads/galleries/'.$result['original']);
        Storage::disk('public')->assertExists('assets/images/uploads/galleries/'.$result['thumbnail']);
    }

    public function test_delete_image_removes_assets_path_from_public_storage_disk(): void
    {
        Storage::fake('public');

        $path = 'assets/images/uploads/delete-me.jpg';
        Storage::disk('public')->put($path, 'test');

        $imageService = app(ImageService::class);
        $imageService->deleteImage($path);

        Storage::disk('public')->assertMissing($path);
    }
}
