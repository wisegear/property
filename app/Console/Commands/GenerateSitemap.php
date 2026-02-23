<?php

namespace App\Console\Commands;

use DOMDocument;
use DOMXPath;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\SitemapGenerator;
use Spatie\Sitemap\SitemapIndex;
use Spatie\Sitemap\Tags\Sitemap as SitemapTag;
use Spatie\Sitemap\Tags\Url;
use Throwable;

class GenerateSitemap extends Command
{
    private const MAX_URLS_PER_SITEMAP = 45000;

    protected $signature = 'sitemap:generate';

    protected $description = 'Generate sitemap.xml and sitemap-index.xml with chunked files';

    public function handle(): int
    {
        $this->info('Generating sitemap.xml and sitemap-index.xml...');

        $sitemap = SitemapGenerator::create(config('app.url'))
            ->shouldCrawl(function ($url) {
                foreach (['/admin', '/login'] as $exclude) {
                    if (str_contains($url, $exclude)) {
                        return false;
                    }
                }

                return true;
            })
            ->getSitemap();

        $areaFile = public_path('data/property_districts.json');
        if (File::exists($areaFile)) {
            $areas = json_decode(File::get($areaFile), true);
            if (is_array($areas)) {
                foreach ($areas as $area) {
                    $path = $area['path'] ?? null;
                    if ($path) {
                        $sitemap->add(Url::create($path));
                    }
                }
            }
        }

        $this->deleteExistingChunkSitemaps();

        $tags = collect($sitemap->getTags())
            ->filter(fn ($tag) => $tag instanceof Url && filled($tag->url))
            ->unique(fn (Url $tag) => $tag->url)
            ->values();

        $chunks = $tags->chunk(self::MAX_URLS_PER_SITEMAP);
        $sitemapIndex = SitemapIndex::create();

        foreach ($chunks as $index => $chunk) {
            $chunkNumber = $index + 1;
            $chunkFilename = $chunkNumber === 1 ? 'sitemap.xml' : "sitemap-{$chunkNumber}.xml";
            $chunkPath = public_path($chunkFilename);

            Sitemap::create()
                ->add($chunk->all())
                ->writeToFile($chunkPath);

            $sitemapIndex->add(SitemapTag::create(url("/{$chunkFilename}")));
        }

        $sitemapIndex->writeToFile(public_path('sitemap-index.xml'));
        $this->removeLastModElementsFromSitemapIndex(public_path('sitemap-index.xml'));

        $this->info('Done: public/sitemap.xml');
        $this->line('Done: public/sitemap-index.xml');
        $this->line('Chunk files generated: '.number_format($chunks->count()));
        $this->line('URLs indexed: '.number_format($tags->count()));

        return self::SUCCESS;
    }

    private function removeLastModElementsFromSitemapIndex(string $sitemapIndexPath): void
    {
        try {
            if (! File::exists($sitemapIndexPath)) {
                return;
            }

            $dom = new DOMDocument('1.0', 'UTF-8');
            $dom->preserveWhiteSpace = false;
            $dom->formatOutput = false;

            if (! $dom->load($sitemapIndexPath)) {
                return;
            }

            if ($dom->documentElement?->localName !== 'sitemapindex') {
                return;
            }

            $namespace = $dom->documentElement->namespaceURI ?: 'http://www.sitemaps.org/schemas/sitemap/0.9';
            $xpath = new DOMXPath($dom);
            $xpath->registerNamespace('sm', $namespace);

            $lastModNodes = $xpath->query('//sm:lastmod');
            if ($lastModNodes === false) {
                return;
            }

            $nodes = [];
            foreach ($lastModNodes as $node) {
                $nodes[] = $node;
            }

            foreach ($nodes as $node) {
                $node->parentNode?->removeChild($node);
            }

            $dom->save($sitemapIndexPath);
        } catch (Throwable) {
        }
    }

    private function deleteExistingChunkSitemaps(): void
    {
        $existingChunkSitemaps = collect(File::files(public_path()))
            ->filter(fn ($file) => preg_match('/^sitemap-(?!index)(?:\d+)\.xml$/', $file->getFilename()) === 1);

        foreach ($existingChunkSitemaps as $file) {
            File::delete($file->getPathname());
        }
    }
}
