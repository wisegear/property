<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;
use Throwable;

class GenerateEpcPostcodeSitemap extends Command
{
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
        $sitemap = Sitemap::create();

        $englandWalesPostcodes = $this->normalisePostcodes((array) data_get($payload, 'postcodes.england_wales', []));
        $scotlandPostcodes = $this->normalisePostcodes((array) data_get($payload, 'postcodes.scotland', []));

        foreach ($englandWalesPostcodes as $postcode) {
            $sitemap->add(
                Url::create('/epc/postcode/'.$this->slugPostcode($postcode))
                    ->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY)
                    ->setLastModificationDate($generatedAt)
            );
        }

        foreach ($scotlandPostcodes as $postcode) {
            $sitemap->add(
                Url::create('/epc/scotland/postcode/'.$this->slugPostcode($postcode))
                    ->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY)
                    ->setLastModificationDate($generatedAt)
            );
        }

        $epcSitemapPath = public_path('sitemap-epc-postcodes.xml');
        $sitemap->writeToFile($epcSitemapPath);

        $indexUpdated = $this->updateExistingSitemapIndexIfPresent($generatedAt->toDateString());

        $this->info('Done: public/sitemap-epc-postcodes.xml');
        $this->line('England & Wales URLs: '.number_format(count($englandWalesPostcodes)));
        $this->line('Scotland URLs: '.number_format(count($scotlandPostcodes)));
        if ($indexUpdated) {
            $this->line('Sitemap index updated.');
        } else {
            $this->line('No sitemap index file found to update.');
        }

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

    private function updateExistingSitemapIndexIfPresent(string $lastModDate): bool
    {
        $indexCandidates = [
            public_path('sitemap-index.xml'),
            public_path('sitemap_index.xml'),
        ];

        $indexPath = collect($indexCandidates)->first(
            fn (string $candidate) => File::exists($candidate)
        );

        if (! is_string($indexPath)) {
            return false;
        }

        try {
            $xml = simplexml_load_file($indexPath);
            if ($xml === false || $xml->getName() !== 'sitemapindex') {
                return false;
            }

            $loc = url('/sitemap-epc-postcodes.xml');
            foreach ($xml->sitemap as $sitemap) {
                if ((string) $sitemap->loc === $loc) {
                    $sitemap->lastmod = $lastModDate;
                    $xml->asXML($indexPath);

                    return true;
                }
            }

            $entry = $xml->addChild('sitemap');
            $entry->addChild('loc', $loc);
            $entry->addChild('lastmod', $lastModDate);
            $xml->asXML($indexPath);

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
