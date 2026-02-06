<?php

namespace App\Console\Commands;

use App\Models\BlogPosts;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class MigrateLegacyBlogImages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'blog:images:migrate-legacy {--dry-run : Report actions without writing changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Move legacy blog images from public assets into storage and update content paths.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $legacyBase = public_path('assets/images/uploads');
        $featuredTarget = 'assets/images/uploads';
        $galleryTarget = 'assets/images/uploads/galleries';
        $inlineTarget = 'assets/images/uploads';

        $this->info($dryRun ? 'Dry run: no changes will be written.' : 'Migrating legacy blog images...');

        $this->migrateFeaturedImages($legacyBase, $featuredTarget, $dryRun);

        $this->migrateDirectory($legacyBase.'/galleries', $galleryTarget, $dryRun);

        $this->migrateInlineImages($legacyBase, $inlineTarget, $dryRun);

        $this->migrateBodyInlinePaths($dryRun);

        $this->info('Migration complete.');

        return self::SUCCESS;
    }

    private function migrateDirectory(string $sourcePath, string $targetPath, bool $dryRun): void
    {
        if (! File::isDirectory($sourcePath)) {
            $this->warn("Missing directory: {$sourcePath}");

            return;
        }

        $disk = Storage::disk('public');
        $disk->makeDirectory($targetPath);

        foreach (File::files($sourcePath) as $file) {
            $filePath = $file->getPathname();

            $target = $targetPath.'/'.$file->getFilename();

            if ($disk->exists($target)) {
                continue;
            }

            $this->line("Move: {$filePath} -> storage/app/public/{$target}");

            if (! $dryRun) {
                $disk->put($target, File::get($filePath));
                File::delete($filePath);
            }
        }
    }

    private function migrateFeaturedImages(string $legacyBase, string $featuredTarget, bool $dryRun): void
    {
        $posts = BlogPosts::query()
            ->whereNotNull('original_image')
            ->get(['id', 'original_image']);

        if ($posts->isEmpty()) {
            return;
        }

        $disk = Storage::disk('public');
        $disk->makeDirectory($featuredTarget);

        foreach ($posts as $post) {
            $filename = $post->original_image;
            $variants = [
                $filename,
                'small_'.$filename,
                'medium_'.$filename,
                'large_'.$filename,
            ];

            foreach ($variants as $variant) {
                $legacyPath = $legacyBase.'/'.$variant;
                $target = $featuredTarget.'/'.$variant;

                if ($disk->exists($target) || ! File::exists($legacyPath)) {
                    continue;
                }

                $this->line("Move featured: {$legacyPath} -> storage/app/public/{$target}");

                if (! $dryRun) {
                    $disk->put($target, File::get($legacyPath));
                    File::delete($legacyPath);
                }
            }
        }
    }

    private function migrateInlineImages(string $legacyBase, string $inlineTarget, bool $dryRun): void
    {
        $posts = BlogPosts::query()
            ->whereNotNull('images')
            ->get(['id', 'images']);

        if ($posts->isEmpty()) {
            return;
        }

        $disk = Storage::disk('public');
        $disk->makeDirectory($inlineTarget);

        foreach ($posts as $post) {
            $images = json_decode($post->images, true) ?? [];
            $updated = [];
            $changed = false;

            foreach ($images as $imagePath) {
                if (! is_string($imagePath) || $imagePath === '') {
                    continue;
                }

                $normalized = ltrim($imagePath, '/');
                $filename = basename($normalized);
                $newPath = $inlineTarget.'/'.$filename;
                $updated[] = $newPath;

                $legacyPath = $legacyBase.'/'.$filename;

                if (! $disk->exists($newPath) && File::exists($legacyPath)) {
                    $this->line("Move inline: {$legacyPath} -> storage/app/public/{$newPath}");
                    if (! $dryRun) {
                        $disk->put($newPath, File::get($legacyPath));
                        File::delete($legacyPath);
                    }
                }

                if ($normalized !== $newPath) {
                    $changed = true;
                }
            }

            if ($changed && ! $dryRun) {
                $post->images = json_encode($updated);
                $post->save();
            }
        }
    }

    private function migrateBodyInlinePaths(bool $dryRun): void
    {
        $appUrl = rtrim(config('app.url'), '/');
        $legacyPaths = [
            $appUrl.'/assets/images/uploads/',
            '/assets/images/uploads/',
            'assets/images/uploads/',
        ];

        $posts = BlogPosts::query()
            ->whereNotNull('body')
            ->get(['id', 'body']);

        foreach ($posts as $post) {
            $updatedBody = str_replace($legacyPaths, $appUrl.'/storage/assets/images/uploads/', $post->body);

            if ($updatedBody === $post->body) {
                continue;
            }

            $this->line("Update body paths for post ID {$post->id}");

            if (! $dryRun) {
                $post->body = $updatedBody;
                $post->save();
            }
        }
    }
}
