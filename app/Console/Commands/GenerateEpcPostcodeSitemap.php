<?php

namespace App\Console\Commands;

use DOMDocument;
use DOMXPath;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\SitemapIndex;
use Spatie\Sitemap\Tags\Sitemap as SitemapTag;
use Spatie\Sitemap\Tags\Url;
use Throwable;

class GenerateEpcPostcodeSitemap extends Command
{
    private const MAX_URLS_PER_SITEMAP = 45000;

    protected $signature = 'sitemap:generate-epc-postcodes';

    protected $description = 'Generate public/sitemap-epc-postcodes.xml from public/data/epc-postcodes.json';

    public function handle(): int
    {
        $indexPath = public_path('data/epc-postcodes.json');
        if (! File::exists($indexPath)) {
            $this->error('Missing postcode index: '.$indexPath);

            return self::FAILURE;
        }

        $payload = json_decode((string) File::get($indexPath), true);
        if (! is_array($payload)) {
            $this->error('Invalid JSON in '.$indexPath);

            return self::FAILURE;
        }

        $generatedAt = now();
        $this->deleteExistingChunkedSitemaps();
        $urls = [];

        $englandWalesPostcodes = $this->normalisePostcodes((array) data_get($payload, 'postcodes.england_wales', []));
        $scotlandPostcodes = $this->normalisePostcodes((array) data_get($payload, 'postcodes.scotland', []));

        foreach ($englandWalesPostcodes as $postcode) {
            $urls[] = Url::create('/epc/postcode/'.$this->slugPostcode($postcode))
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY)
                ->setLastModificationDate($generatedAt);
        }

        foreach ($scotlandPostcodes as $postcode) {
            $urls[] = Url::create('/epc/scotland/postcode/'.$this->slugPostcode($postcode))
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY)
                ->setLastModificationDate($generatedAt);
        }

        $sitemapLocations = [];
        $epcSitemapPath = public_path('sitemap-epc-postcodes.xml');
        if (count($urls) <= self::MAX_URLS_PER_SITEMAP) {
            Sitemap::create()
                ->add($urls)
                ->writeToFile($epcSitemapPath);

            $sitemapLocations[] = url('/sitemap-epc-postcodes.xml');
        } else {
            $chunks = collect($urls)->chunk(self::MAX_URLS_PER_SITEMAP);
            $epcSitemapIndex = SitemapIndex::create();

            foreach ($chunks as $index => $chunk) {
                $chunkNumber = $index + 1;
                $chunkFilename = "sitemap-epc-postcodes-{$chunkNumber}.xml";
                $chunkPath = public_path($chunkFilename);
                $chunkLocation = url("/{$chunkFilename}");

                Sitemap::create()
                    ->add($chunk->all())
                    ->writeToFile($chunkPath);

                $epcSitemapIndex->add(
                    SitemapTag::create($chunkLocation)->setLastModificationDate($generatedAt)
                );
                $sitemapLocations[] = $chunkLocation;
            }

            $epcSitemapIndex->writeToFile($epcSitemapPath);
        }

        $indexUpdated = $this->updateExistingSitemapIndexIfPresent(
            $generatedAt->toDateString(),
            $sitemapLocations
        );

        $this->info('Done: public/sitemap-epc-postcodes.xml');
        $this->line('England & Wales URLs: '.number_format(count($englandWalesPostcodes)));
        $this->line('Scotland URLs: '.number_format(count($scotlandPostcodes)));
        if ($indexUpdated) {
            $this->line('Sitemap index updated.');
        } else {
            $this->line('No sitemap index file found to update.');
        }
        $this->line('EPC sitemap files in index: '.number_format(count($sitemapLocations)));

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function normalisePostcodes(array $postcodes): array
    {
        return collect($postcodes)
            ->map(fn ($postcode) => strtoupper(trim((string) $postcode)))
            ->filter(fn (string $postcode) => $postcode !== '')
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function slugPostcode(string $postcode): string
    {
        return str_replace(' ', '-', strtoupper(trim($postcode)));
    }

    /**
     * @param  array<int, string>  $sitemapLocations
     */
    private function updateExistingSitemapIndexIfPresent(string $lastModDate, array $sitemapLocations): bool
    {
        $indexCandidates = [
            public_path('sitemap.xml'),
            public_path('sitemap-index.xml'),
            public_path('sitemap_index.xml'),
        ];

        foreach ($indexCandidates as $indexPath) {
            if (! File::exists($indexPath)) {
                continue;
            }

            try {
                $dom = new DOMDocument('1.0', 'UTF-8');
                $dom->preserveWhiteSpace = false;
                $dom->formatOutput = false;
                if (! $dom->load($indexPath)) {
                    continue;
                }

                if ($dom->documentElement?->localName !== 'sitemapindex') {
                    continue;
                }

                $namespace = $dom->documentElement->namespaceURI ?: 'http://www.sitemaps.org/schemas/sitemap/0.9';
                $xpath = new DOMXPath($dom);
                $xpath->registerNamespace('sm', $namespace);

                $sitemapNodes = $xpath->query('//sm:sitemap');
                if ($sitemapNodes !== false) {
                    $nodes = [];
                    foreach ($sitemapNodes as $node) {
                        $nodes[] = $node;
                    }

                    foreach ($nodes as $sitemapNode) {
                        $locNode = $xpath->query('./sm:loc', $sitemapNode)?->item(0);
                        if (! $locNode) {
                            continue;
                        }

                        $path = parse_url((string) $locNode->textContent, PHP_URL_PATH) ?? '';
                        if (preg_match('/^\/sitemap-epc-postcodes(?:-\d+)?\.xml$/', $path) === 1) {
                            $sitemapNode->parentNode?->removeChild($sitemapNode);
                        }
                    }
                }

                foreach ($sitemapLocations as $location) {
                    $sitemapElement = $dom->createElementNS($namespace, 'sitemap');
                    $locElement = $dom->createElementNS($namespace, 'loc', $location);
                    $lastModElement = $dom->createElementNS($namespace, 'lastmod', $lastModDate);
                    $sitemapElement->appendChild($locElement);
                    $sitemapElement->appendChild($lastModElement);
                    $dom->documentElement->appendChild($sitemapElement);
                }

                $dom->save($indexPath);

                return true;
            } catch (Throwable) {
                continue;
            }
        }

        return false;
    }

    private function deleteExistingChunkedSitemaps(): void
    {
        $existingChunkSitemaps = collect(File::files(public_path()))
            ->filter(fn ($file) => preg_match('/^sitemap-epc-postcodes-\d+\.xml$/', $file->getFilename()) === 1);

        foreach ($existingChunkSitemaps as $file) {
            File::delete($file->getPathname());
        }
    }
}
