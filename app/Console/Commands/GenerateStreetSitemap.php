<?php

namespace App\Console\Commands;

use App\Http\Controllers\PropertyStreetController;
use DOMDocument;
use DOMXPath;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\SitemapIndex;
use Spatie\Sitemap\Tags\Sitemap as SitemapTag;
use Spatie\Sitemap\Tags\Url;
use Throwable;

class GenerateStreetSitemap extends Command
{
    private const MAX_URLS_PER_SITEMAP = 45000;

    private const MIN_SALES_PER_STREET_PAGE = 5;

    protected $signature = 'sitemap:generate-streets {--chunk-size=45000}';

    protected $description = 'Generate public/sitemap-streets.xml from qualifying street and outcode combinations';

    public function handle(): int
    {
        if (! Schema::hasTable('land_registry')) {
            $this->error('Missing land_registry table.');

            return self::FAILURE;
        }

        $chunkSize = $this->chunkSize();

        $this->deleteExistingStreetSitemaps();

        $sitemapLocations = [];
        $partCounts = [];
        $buffer = [];
        $totalUrls = 0;
        $chunkNumber = 1;
        $isChunked = false;

        foreach ($this->qualifyingStreetRows()->cursor() as $row) {
            $buffer[] = $this->streetUrlTag($row);
            $totalUrls++;

            if (! $isChunked && count($buffer) > $chunkSize) {
                $overflow = array_pop($buffer);

                $sitemapLocations[] = $this->writeChunkFile($chunkNumber, $buffer);
                $partCounts[$chunkNumber] = count($buffer);

                $buffer = [$overflow];
                $chunkNumber++;
                $isChunked = true;

                continue;
            }

            if ($isChunked && count($buffer) === $chunkSize) {
                $sitemapLocations[] = $this->writeChunkFile($chunkNumber, $buffer);
                $partCounts[$chunkNumber] = count($buffer);

                $buffer = [];
                $chunkNumber++;
            }
        }

        $streetSitemapPath = public_path('sitemap-streets.xml');

        if (! $isChunked) {
            Sitemap::create()
                ->add($buffer)
                ->writeToFile($streetSitemapPath);

            $sitemapLocations[] = url('/sitemap-streets.xml');
            $partCounts[1] = count($buffer);
        } else {
            if ($buffer !== []) {
                $sitemapLocations[] = $this->writeChunkFile($chunkNumber, $buffer);
                $partCounts[$chunkNumber] = count($buffer);
            }

            $streetSitemapIndex = SitemapIndex::create();

            foreach ($sitemapLocations as $location) {
                $streetSitemapIndex->add(SitemapTag::create($location));
            }

            $streetSitemapIndex->writeToFile($streetSitemapPath);
            $this->removeLastModElementsFromSitemapIndex($streetSitemapPath);
        }

        $indexUpdated = $this->updateExistingSitemapIndexIfPresent($sitemapLocations);

        $this->info('Done: public/sitemap-streets.xml');
        $this->line('Qualifying street+outcode URLs: '.number_format($totalUrls));
        $this->line('Street sitemap part files created: '.number_format(count($partCounts)));
        foreach ($partCounts as $partNumber => $urlCount) {
            $this->line(sprintf('Street sitemap part %d URLs: %s', $partNumber, number_format($urlCount)));
        }
        $this->line('Per-file URL limit: '.number_format($chunkSize).' (capped at '.number_format(self::MAX_URLS_PER_SITEMAP).')');
        if ($indexUpdated) {
            $this->line('Sitemap index updated.');
        } else {
            $this->line('No sitemap index file found to update.');
        }
        $this->line('Street sitemap files in index: '.number_format(count($sitemapLocations)));

        return self::SUCCESS;
    }

    private function chunkSize(): int
    {
        return max(1, min((int) $this->option('chunk-size'), self::MAX_URLS_PER_SITEMAP));
    }

    private function qualifyingStreetRows()
    {
        $outcodeExpression = $this->outcodeExpression();

        return DB::table('land_registry')
            ->selectRaw('"Street" as street')
            ->selectRaw($outcodeExpression.' as outcode')
            ->selectRaw('COUNT(*) as sales_count')
            ->selectRaw('MAX("Date") as last_modified')
            ->where('PPDCategoryType', 'A')
            ->whereNotNull('Street')
            ->whereRaw('TRIM("Street") <> ?', [''])
            ->whereNotNull('Postcode')
            ->whereRaw('TRIM("Postcode") <> ?', [''])
            ->groupByRaw('"Street", '.$outcodeExpression)
            ->havingRaw('COUNT(*) >= ?', [self::MIN_SALES_PER_STREET_PAGE])
            ->orderByRaw($outcodeExpression)
            ->orderBy('Street');
    }

    private function outcodeExpression(): string
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            return 'UPPER(SPLIT_PART("Postcode", \' \', 1))';
        }

        return 'UPPER(TRIM(SUBSTR("Postcode", 1, CASE WHEN INSTR("Postcode", \' \') = 0 THEN LENGTH("Postcode") ELSE INSTR("Postcode", \' \') - 1 END)))';
    }

    private function streetUrlTag(object $row): Url
    {
        $street = trim((string) $row->street);
        $outcode = strtoupper(trim((string) $row->outcode));

        $url = Url::create(PropertyStreetController::streetPath($outcode, Str::slug($street)));

        if ($row->last_modified !== null) {
            $url->setLastModificationDate(Carbon::parse((string) $row->last_modified));
        }

        return $url;
    }

    /**
     * @param  array<int, Url>  $urls
     */
    private function writeChunkFile(int $chunkNumber, array $urls): string
    {
        $chunkFilename = "sitemap-streets-{$chunkNumber}.xml";

        Sitemap::create()
            ->add($urls)
            ->writeToFile(public_path($chunkFilename));

        return url("/{$chunkFilename}");
    }

    /**
     * @param  array<int, string>  $sitemapLocations
     */
    private function updateExistingSitemapIndexIfPresent(array $sitemapLocations): bool
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

                $lastModNodes = $xpath->query('//sm:lastmod');
                if ($lastModNodes !== false) {
                    $nodes = [];
                    foreach ($lastModNodes as $node) {
                        $nodes[] = $node;
                    }

                    foreach ($nodes as $node) {
                        $node->parentNode?->removeChild($node);
                    }
                }

                $existingLocs = [];
                $sitemapNodes = $xpath->query('//sm:sitemap');
                if ($sitemapNodes !== false) {
                    foreach ($sitemapNodes as $sitemapNode) {
                        $locNode = $xpath->query('sm:loc', $sitemapNode)?->item(0);
                        if (! $locNode) {
                            continue;
                        }

                        $loc = trim((string) $locNode->textContent);
                        if ($loc === '') {
                            continue;
                        }

                        if (str_contains($loc, '/sitemap-streets')) {
                            $sitemapNode->parentNode?->removeChild($sitemapNode);

                            continue;
                        }

                        $existingLocs[$loc] = true;
                    }
                }

                foreach ($sitemapLocations as $location) {
                    if (isset($existingLocs[$location])) {
                        continue;
                    }

                    $sitemapElement = $dom->createElementNS($namespace, 'sitemap');
                    $locElement = $dom->createElementNS($namespace, 'loc', $location);
                    $sitemapElement->appendChild($locElement);
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

    private function deleteExistingStreetSitemaps(): void
    {
        $files = collect(File::files(public_path()))
            ->filter(fn ($file) => preg_match('/^sitemap-streets-\d+\.xml$/', $file->getFilename()) === 1);

        foreach ($files as $file) {
            File::delete($file->getPathname());
        }
    }
}
