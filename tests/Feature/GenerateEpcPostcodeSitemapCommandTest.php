<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class GenerateEpcPostcodeSitemapCommandTest extends TestCase
{
    private ?string $originalPostcodeIndex = null;

    private ?string $originalEpcSitemap = null;

    private ?string $originalSitemapIndex = null;

    /**
     * @var array<string, string>
     */
    private array $originalChunkSitemaps = [];

    protected function setUp(): void
    {
        parent::setUp();

        $postcodeIndexPath = public_path('data/epc-postcodes.json');
        $epcSitemapPath = public_path('sitemap-epc-postcodes.xml');
        $sitemapIndexPath = public_path('sitemap-index.xml');

        $this->originalPostcodeIndex = File::exists($postcodeIndexPath) ? File::get($postcodeIndexPath) : null;
        $this->originalEpcSitemap = File::exists($epcSitemapPath) ? File::get($epcSitemapPath) : null;
        $this->originalSitemapIndex = File::exists($sitemapIndexPath) ? File::get($sitemapIndexPath) : null;
        $this->originalChunkSitemaps = collect(File::files(public_path()))
            ->filter(fn ($file) => preg_match('/^sitemap-epc-postcodes-\d+\.xml$/', $file->getFilename()) === 1)
            ->mapWithKeys(fn ($file) => [$file->getFilename() => File::get($file->getPathname())])
            ->all();
    }

    protected function tearDown(): void
    {
        $this->restoreFile(public_path('data/epc-postcodes.json'), $this->originalPostcodeIndex);
        $this->restoreFile(public_path('sitemap-epc-postcodes.xml'), $this->originalEpcSitemap);
        $this->restoreFile(public_path('sitemap-index.xml'), $this->originalSitemapIndex);
        $this->deleteChunkSitemaps();

        foreach ($this->originalChunkSitemaps as $filename => $contents) {
            File::put(public_path($filename), $contents);
        }

        parent::tearDown();
    }

    public function test_command_generates_epc_postcode_sitemap_from_index_json(): void
    {
        $this->writePostcodeIndex([
            'england_wales' => ['AL1 1BH', 'KA7 3XY'],
            'scotland' => ['EH1 1YZ'],
        ]);

        $this->artisan('sitemap:generate-epc-postcodes')->assertExitCode(0);

        $sitemapPath = public_path('sitemap-epc-postcodes.xml');
        $this->assertFileExists($sitemapPath);

        $xml = simplexml_load_file($sitemapPath);
        $this->assertNotFalse($xml);
        $this->assertSame('urlset', $xml->getName());
        $this->assertCount(3, $xml->url);

        $xmlString = (string) File::get($sitemapPath);
        $this->assertStringContainsString('/epc/postcode/AL1-1BH', $xmlString);
        $this->assertStringContainsString('/epc/postcode/KA7-3XY', $xmlString);
        $this->assertStringContainsString('/epc/scotland/postcode/EH1-1YZ', $xmlString);
        $this->assertStringNotContainsString('<changefreq>', $xmlString);
        $this->assertStringNotContainsString('<lastmod>', $xmlString);
    }

    public function test_command_adds_epc_sitemap_to_existing_sitemap_index(): void
    {
        $this->writePostcodeIndex([
            'england_wales' => ['AL1 1BH'],
            'scotland' => ['EH1 1YZ'],
        ]);

        File::put(
            public_path('sitemap-index.xml'),
            '<?xml version="1.0" encoding="UTF-8"?>'
            .'<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
            .'<sitemap><loc>'.url('/sitemap.xml').'</loc><lastmod>2026-01-01</lastmod></sitemap>'
            .'</sitemapindex>'
        );

        $this->artisan('sitemap:generate-epc-postcodes')->assertExitCode(0);
        $this->artisan('sitemap:generate-epc-postcodes')->assertExitCode(0);

        $targetLoc = url('/sitemap-epc-postcodes.xml');
        $matchingLocCount = collect([
            public_path('sitemap.xml'),
            public_path('sitemap-index.xml'),
            public_path('sitemap_index.xml'),
        ])->filter(fn ($path) => File::exists($path))
            ->sum(function ($path) use ($targetLoc) {
                $xml = @simplexml_load_file($path);
                if ($xml === false || $xml->getName() !== 'sitemapindex') {
                    return 0;
                }

                $xmlString = (string) File::get($path);

                return str_contains($xmlString, $targetLoc) ? 1 : 0;
            });

        $this->assertSame(1, $matchingLocCount);
    }

    public function test_epc_postcode_sitemap_route_serves_xml_file(): void
    {
        File::put(
            public_path('sitemap-epc-postcodes.xml'),
            '<?xml version="1.0" encoding="UTF-8"?><urlset></urlset>'
        );

        $response = $this->get('/sitemap-epc-postcodes.xml');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/xml; charset=UTF-8');
    }

    public function test_epc_postcode_sitemap_is_chunked_when_over_45000_urls(): void
    {
        $englandWales = [];
        for ($index = 1; $index <= 45001; $index++) {
            $englandWales[] = sprintf('A%04d 1AA', $index);
        }

        $this->writePostcodeIndex([
            'england_wales' => $englandWales,
            'scotland' => [],
        ]);

        $this->artisan('sitemap:generate-epc-postcodes')->assertExitCode(0);

        $this->assertFileExists(public_path('sitemap-epc-postcodes.xml'));
        $this->assertFileExists(public_path('sitemap-epc-postcodes-1.xml'));
        $this->assertFileExists(public_path('sitemap-epc-postcodes-2.xml'));
        $this->assertFileDoesNotExist(public_path('sitemap-epc-postcodes-3.xml'));

        $indexXml = simplexml_load_file(public_path('sitemap-epc-postcodes.xml'));
        $this->assertNotFalse($indexXml);
        $this->assertSame('sitemapindex', $indexXml->getName());

        $chunkOne = simplexml_load_file(public_path('sitemap-epc-postcodes-1.xml'));
        $chunkTwo = simplexml_load_file(public_path('sitemap-epc-postcodes-2.xml'));
        $this->assertNotFalse($chunkOne);
        $this->assertNotFalse($chunkTwo);
        $this->assertCount(45000, $chunkOne->url);
        $this->assertCount(1, $chunkTwo->url);
    }

    public function test_epc_postcode_chunk_sitemap_route_serves_xml_file(): void
    {
        File::put(
            public_path('sitemap-epc-postcodes-1.xml'),
            '<?xml version="1.0" encoding="UTF-8"?><urlset></urlset>'
        );

        $response = $this->get('/sitemap-epc-postcodes-1.xml');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/xml; charset=UTF-8');
    }

    private function writePostcodeIndex(array $postcodes): void
    {
        File::ensureDirectoryExists(public_path('data'));
        File::put(
            public_path('data/epc-postcodes.json'),
            json_encode([
                'meta' => [
                    'generated_at' => now()->toIso8601String(),
                    'min_certificates' => 30,
                    'from_year' => 2015,
                ],
                'postcodes' => $postcodes,
            ], JSON_THROW_ON_ERROR)
        );
    }

    private function restoreFile(string $path, ?string $contents): void
    {
        File::ensureDirectoryExists(dirname($path));

        if ($contents === null) {
            if (File::exists($path)) {
                File::delete($path);
            }

            return;
        }

        File::put($path, $contents);
    }

    private function deleteChunkSitemaps(): void
    {
        collect(File::files(public_path()))
            ->filter(fn ($file) => preg_match('/^sitemap-epc-postcodes-\d+\.xml$/', $file->getFilename()) === 1)
            ->each(fn ($file) => File::delete($file->getPathname()));
    }
}
