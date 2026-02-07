<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Mockery;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\SitemapGenerator;
use Spatie\Sitemap\Tags\Url;
use Tests\TestCase;

class GenerateSitemapCommandTest extends TestCase
{
    private ?string $originalSitemapIndex = null;

    private ?string $originalSitemapMasterIndex = null;

    private ?string $originalEpcSitemap = null;

    private ?string $originalEpcPostcodeIndex = null;

    private ?string $originalPropertyDistricts = null;

    /**
     * @var array<string, string>
     */
    private array $originalChunkSitemaps = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalSitemapIndex = File::exists(public_path('sitemap.xml'))
            ? File::get(public_path('sitemap.xml'))
            : null;

        $this->originalEpcSitemap = File::exists(public_path('sitemap-epc-postcodes.xml'))
            ? File::get(public_path('sitemap-epc-postcodes.xml'))
            : null;

        $this->originalSitemapMasterIndex = File::exists(public_path('sitemap-index.xml'))
            ? File::get(public_path('sitemap-index.xml'))
            : null;

        $this->originalEpcPostcodeIndex = File::exists(public_path('data/epc-postcodes.json'))
            ? File::get(public_path('data/epc-postcodes.json'))
            : null;

        $this->originalPropertyDistricts = File::exists(public_path('data/property_districts.json'))
            ? File::get(public_path('data/property_districts.json'))
            : null;

        $this->originalChunkSitemaps = collect(File::files(public_path()))
            ->filter(fn ($file) => preg_match('/^sitemap-\d+\.xml$/', $file->getFilename()) === 1)
            ->mapWithKeys(fn ($file) => [$file->getFilename() => File::get($file->getPathname())])
            ->all();
    }

    protected function tearDown(): void
    {
        $this->deleteChunkSitemaps();

        foreach ($this->originalChunkSitemaps as $filename => $contents) {
            File::put(public_path($filename), $contents);
        }

        $this->restoreFile(public_path('sitemap.xml'), $this->originalSitemapIndex);
        $this->restoreFile(public_path('sitemap-index.xml'), $this->originalSitemapMasterIndex);
        $this->restoreFile(public_path('sitemap-epc-postcodes.xml'), $this->originalEpcSitemap);
        $this->restoreFile(public_path('data/epc-postcodes.json'), $this->originalEpcPostcodeIndex);
        $this->restoreFile(public_path('data/property_districts.json'), $this->originalPropertyDistricts);

        parent::tearDown();
    }

    public function test_command_generates_sitemap_index_and_45000_url_chunks(): void
    {
        $this->writePostcodeIndex([
            'england_wales' => [],
            'scotland' => [],
        ]);
        File::put(public_path('data/property_districts.json'), '[]');
        $this->mockSitemapGeneratorWithUrlCount(45001);

        $this->artisan('sitemap:generate')->assertExitCode(0);

        $this->assertFileExists(public_path('sitemap.xml'));
        $this->assertFileExists(public_path('sitemap-2.xml'));
        $this->assertFileDoesNotExist(public_path('sitemap-3.xml'));

        $chunkOne = simplexml_load_file(public_path('sitemap.xml'));
        $chunkTwo = simplexml_load_file(public_path('sitemap-2.xml'));

        $this->assertNotFalse($chunkOne);
        $this->assertNotFalse($chunkTwo);
        $this->assertSame('urlset', $chunkOne->getName());
        $this->assertSame('urlset', $chunkTwo->getName());
        $this->assertCount(45000, $chunkOne->url);
        $this->assertCount(1, $chunkTwo->url);

        $index = simplexml_load_file(public_path('sitemap-index.xml'));
        $this->assertNotFalse($index);
        $this->assertSame('sitemapindex', $index->getName());

        $indexXml = (string) File::get(public_path('sitemap-index.xml'));
        $this->assertStringContainsString(url('/sitemap.xml'), $indexXml);
        $this->assertStringContainsString(url('/sitemap-2.xml'), $indexXml);
    }

    public function test_command_removes_stale_chunk_sitemaps_before_writing_new_chunks(): void
    {
        $this->writePostcodeIndex([
            'england_wales' => [],
            'scotland' => [],
        ]);
        File::put(public_path('data/property_districts.json'), '[]');
        File::put(public_path('sitemap-9.xml'), '<?xml version="1.0" encoding="UTF-8"?><urlset></urlset>');
        $this->mockSitemapGeneratorWithUrlCount(1);

        $this->artisan('sitemap:generate')->assertExitCode(0);

        $this->assertFileDoesNotExist(public_path('sitemap-9.xml'));
        $this->assertFileExists(public_path('sitemap.xml'));
        $this->assertFileDoesNotExist(public_path('sitemap-2.xml'));
    }

    public function test_sitemap_index_route_serves_xml_file(): void
    {
        File::put(public_path('sitemap.xml'), '<?xml version="1.0" encoding="UTF-8"?><urlset></urlset>');

        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/xml; charset=UTF-8');
    }

    public function test_sitemap_master_index_route_serves_xml_file(): void
    {
        File::put(public_path('sitemap-index.xml'), '<?xml version="1.0" encoding="UTF-8"?><sitemapindex></sitemapindex>');

        $response = $this->get('/sitemap-index.xml');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/xml; charset=UTF-8');
    }

    public function test_chunk_sitemap_route_serves_xml_file(): void
    {
        File::put(public_path('sitemap-1.xml'), '<?xml version="1.0" encoding="UTF-8"?><urlset></urlset>');

        $response = $this->get('/sitemap-1.xml');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/xml; charset=UTF-8');
    }

    private function mockSitemapGeneratorWithUrlCount(int $urlCount): void
    {
        $sitemap = Sitemap::create();

        for ($index = 1; $index <= $urlCount; $index++) {
            $sitemap->add(Url::create('/generated-'.$index));
        }

        $generator = Mockery::mock(SitemapGenerator::class);
        $generator->shouldReceive('setUrl')->andReturnSelf();
        $generator->shouldReceive('shouldCrawl')->andReturnSelf();
        $generator->shouldReceive('getSitemap')->andReturn($sitemap);

        $this->app->instance(SitemapGenerator::class, $generator);
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

    private function deleteChunkSitemaps(): void
    {
        collect(File::files(public_path()))
            ->filter(fn ($file) => preg_match('/^sitemap-\d+\.xml$/', $file->getFilename()) === 1)
            ->each(fn ($file) => File::delete($file->getPathname()));
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
}
