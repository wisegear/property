<?php

namespace App\Services;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;

class ImageService
{
    private const FEATURED_PATH = 'assets/images/uploads/';

    private const INLINE_PATH = 'assets/images/uploads/';

    private const GALLERY_PATH = 'assets/images/uploads/galleries/';

    private const LINK_PATH = 'assets/images/uploads/';

    public function handleImageUpload(UploadedFile $image): string
    {
        // Generate a unique image name without any prefix for the original image
        $imageName = pathinfo($image->getClientOriginalName(), PATHINFO_FILENAME)
                   .'_'.time().'.'.$image->getClientOriginalExtension();

        // Define file names for the resized images
        $smallImageName = 'small_'.$imageName;
        $mediumImageName = 'medium_'.$imageName;
        $largeImageName = 'large_'.$imageName;

        $disk = Storage::disk('public');
        $disk->makeDirectory(self::FEATURED_PATH);

        // Create resized versions based on the original image
        $originalPath = self::FEATURED_PATH.$imageName;
        $disk->putFileAs(self::FEATURED_PATH, $image, $imageName);
        $sourcePath = $image->getRealPath();

        if ($sourcePath === false) {
            throw new \RuntimeException('Uploaded image is not readable.');
        }

        // Small image: 350x200, 50% quality
        Image::read($sourcePath)
            ->cover(350, 200, 'center')
            ->save($disk->path(self::FEATURED_PATH.$smallImageName), 50);

        // Medium image: 800x300, 75% quality
        // No cropping. Force the image into the exact 800x300 frame (may squish if aspect ratio differs).
        // Prevent upscaling beyond the source.
        $medium = Image::read($sourcePath);
        if ($medium->width() > 800 || $medium->height() > 300) {
            $medium->resize(800, 300);
        }
        $medium->save($disk->path(self::FEATURED_PATH.$mediumImageName), 75);

        // Large image: 1200x400, 75% quality
        // No cropping. Force the image into the exact 1200x400 frame (may squish if aspect ratio differs).
        // Prevent upscaling beyond the source.
        $large = Image::read($sourcePath);
        if ($large->width() > 1200 || $large->height() > 400) {
            $large->resize(1200, 400);
        }
        $large->save($disk->path(self::FEATURED_PATH.$largeImageName), 75);

        // Return only the original image's file name (not the full path)
        return $imageName;
    }

    public function deleteImage(string|array $imagePaths): void
    {
        $disk = Storage::disk('public');
        // If $imagePaths is an array of multiple image paths
        if (is_array($imagePaths)) {
            foreach ($imagePaths as $imagePath) {
                $this->deleteSingleImage($imagePath, $disk);
            }
        } elseif (is_string($imagePaths)) {
            $this->deleteSingleImage($imagePaths, $disk);
        }
    }

    public function optimizeAndSaveImage(UploadedFile $image, string $path = self::INLINE_PATH): string
    {
        // Generate a unique image name
        $imageName = pathinfo($image->getClientOriginalName(), PATHINFO_FILENAME).'_'.time().'.'.$image->getClientOriginalExtension();

        $disk = Storage::disk('public');
        $disk->makeDirectory($path);

        Image::read($image->getRealPath())
            ->save($disk->path($path.$imageName), 50);  // Save with 50% quality to reduce file size

        return $path.$imageName;
    }

    public function handleLinkImageUpload(UploadedFile $image): string
    {
        // Generate a unique image name
        $imageName = 'link_'.pathinfo($image->getClientOriginalName(), PATHINFO_FILENAME).'_'.time().'.'.$image->getClientOriginalExtension();
        $disk = Storage::disk('public');
        $disk->makeDirectory(self::LINK_PATH);

        // Read, resize, crop, and save the image at 200x200 pixels
        Image::read($image->getRealPath())
            ->resize(200, 200, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            })
            ->crop(200, 200)
            ->save($disk->path(self::LINK_PATH.$imageName), 75);

        return $imageName; // Return only the image name
    }

    /**
     * Handles uploading gallery images.
     *
     * Saves the original image to storage/app/public/blog/galleries and creates a 350x200 thumbnail.
     * Returns an array containing the filenames for both the original and thumbnail.
     */
    /**
     * @return array{original: string, thumbnail: string}
     */
    public function handleGalleryImageUpload(UploadedFile $image): array
    {
        // Generate a unique image name
        $imageName = pathinfo($image->getClientOriginalName(), PATHINFO_FILENAME)
                   .'_'.time().'.'.$image->getClientOriginalExtension();

        $disk = Storage::disk('public');
        $disk->makeDirectory(self::GALLERY_PATH);

        // Define paths for the original and thumbnail images
        $originalPath = self::GALLERY_PATH.$imageName;
        $thumbnailPath = self::GALLERY_PATH.'thumbnail_'.$imageName;

        // Move the original image to the destination directory
        $disk->putFileAs(self::GALLERY_PATH, $image, $imageName);

        // Create the thumbnail (350x200)
        Image::read($disk->path($originalPath))
            ->resize(350, 200, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            })
            ->crop(350, 200)
            ->save($disk->path($thumbnailPath), 70);

        // Return an array with both filenames
        return [
            'original' => $imageName,
            'thumbnail' => 'thumbnail_'.$imageName,
        ];
    }

    private function deleteSingleImage(?string $imagePath, FilesystemAdapter $disk): void
    {
        if (empty($imagePath)) {
            return;
        }

        $normalizedPath = str_replace('\\', '/', ltrim($imagePath, '/'));

        if (Str::startsWith($normalizedPath, 'assets/')) {
            $disk->delete($normalizedPath);

            $publicPath = public_path($normalizedPath);
            if (File::exists($publicPath)) {
                File::delete($publicPath);
            }

            return;
        }

        if (Str::startsWith($normalizedPath, 'storage/')) {
            $normalizedPath = Str::after($normalizedPath, 'storage/');
        }

        $disk->delete($normalizedPath);
    }
}
