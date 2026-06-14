<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GenerateStreetSitemapCommandTest extends TestCase
{
    private ?string $originalStreetSitemap = null;

    private ?string $originalSitemapIndex = null;

    /**
     * @var array<string, string>
     */
    private array $originalChunkSitemaps = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureLandRegistryTable();

        $streetSitemapPath = public_path('sitemap-streets.xml');
        $sitemapIndexPath = public_path('sitemap-index.xml');

        $this->originalStreetSitemap = File::exists($streetSitemapPath) ? File::get($streetSitemapPath) : null;
        $this->originalSitemapIndex = File::exists($sitemapIndexPath) ? File::get($sitemapIndexPath) : null;
        $this->originalChunkSitemaps = collect(File::files(public_path()))
            ->filter(fn ($file) => preg_match('/^sitemap-streets-\d+\.xml$/', $file->getFilename()) === 1)
            ->mapWithKeys(fn ($file) => [$file->getFilename() => File::get($file->getPathname())])
            ->all();

        DB::table('land_registry')->delete();
    }

    protected function tearDown(): void
    {
        DB::table('land_registry')->delete();

        $this->restoreFile(public_path('sitemap-streets.xml'), $this->originalStreetSitemap);
        $this->restoreFile(public_path('sitemap-index.xml'), $this->originalSitemapIndex);
        $this->deleteChunkSitemaps();

        foreach ($this->originalChunkSitemaps as $filename => $contents) {
            File::put(public_path($filename), $contents);
        }

        parent::tearDown();
    }

    public function test_command_generates_street_sitemap_for_qualifying_street_and_outcode_pages(): void
    {
        DB::table('land_registry')->insert([
            $this->saleRow('tx-001', 'MAIN STREET', 'B79 7AA', '2024-01-01 00:00:00'),
            $this->saleRow('tx-002', 'MAIN STREET', 'B79 7AB', '2024-01-02 00:00:00'),
            $this->saleRow('tx-003', 'MAIN STREET', 'B79 7AC', '2024-01-03 00:00:00'),
            $this->saleRow('tx-004', 'MAIN STREET', 'B79 7AD', '2024-01-04 00:00:00'),
            $this->saleRow('tx-005', 'MAIN STREET', 'B79 7AE', '2024-01-05 00:00:00'),
            $this->saleRow('tx-006', 'MAIN STREET', 'B80 1AA', '2024-01-06 00:00:00'),
            $this->saleRow('tx-007', 'MAIN STREET', 'B80 1AB', '2024-01-07 00:00:00'),
            $this->saleRow('tx-008', 'MAIN STREET', 'B80 1AC', '2024-01-08 00:00:00'),
            $this->saleRow('tx-009', 'MAIN STREET', 'B80 1AD', '2024-01-09 00:00:00'),
            $this->saleRow('tx-010', 'HIGH ROAD', 'B79 8AA', '2024-01-10 00:00:00'),
            $this->saleRow('tx-011', 'HIGH ROAD', 'B79 8AB', '2024-01-11 00:00:00'),
            $this->saleRow('tx-012', 'HIGH ROAD', 'B79 8AC', '2024-01-12 00:00:00'),
            $this->saleRow('tx-013', 'HIGH ROAD', 'B79 8AD', '2024-01-13 00:00:00'),
            $this->saleRow('tx-014', 'HIGH ROAD', 'B79 8AE', '2024-01-14 00:00:00'),
            $this->saleRow('tx-015', 'SIDE LANE', 'B81 1AA', '2024-01-15 00:00:00'),
            $this->saleRow('tx-016', 'SIDE LANE', 'B81 1AB', '2024-01-16 00:00:00'),
            $this->saleRow('tx-017', 'SIDE LANE', 'B81 1AC', '2024-01-17 00:00:00'),
            $this->saleRow('tx-018', 'SIDE LANE', 'B81 1AD', '2024-01-18 00:00:00'),
            $this->saleRow('tx-019', 'B STREET', 'B82 1AA', '2024-01-19 00:00:00', 'B'),
            $this->saleRow('tx-020', '', 'B83 1AA', '2024-01-20 00:00:00'),
            $this->saleRow('tx-021', 'EMPTY POSTCODE ROAD', '', '2024-01-21 00:00:00'),
        ]);

        File::put(
            public_path('sitemap-index.xml'),
            '<?xml version="1.0" encoding="UTF-8"?>'
            .'<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
            .'<sitemap><loc>'.url('/sitemap.xml').'</loc></sitemap>'
            .'</sitemapindex>'
        );

        $this->artisan('sitemap:generate-streets')->assertExitCode(0);

        $sitemapPath = public_path('sitemap-streets.xml');
        $this->assertFileExists($sitemapPath);

        $xml = simplexml_load_file($sitemapPath);
        $this->assertNotFalse($xml);
        $this->assertSame('urlset', $xml->getName());
        $this->assertCount(2, $xml->url);

        $xmlString = (string) File::get($sitemapPath);
        $this->assertStringContainsString('/property/street/high-road?outcode=B79', $xmlString);
        $this->assertStringContainsString('/property/street/main-street?outcode=B79', $xmlString);
        $this->assertStringNotContainsString('/property/street/main-street?outcode=B80', $xmlString);
        $this->assertStringNotContainsString('/property/street/side-lane?outcode=B81', $xmlString);
        $this->assertStringContainsString('2024-01-14', $xmlString);

        $indexXmlString = (string) File::get(public_path('sitemap-index.xml'));
        $this->assertStringContainsString(url('/sitemap-streets.xml'), $indexXmlString);
        $this->assertStringContainsString(url('/sitemap.xml'), $indexXmlString);
    }

    public function test_command_chunks_street_sitemaps_without_exceeding_the_per_file_limit(): void
    {
        DB::table('land_registry')->insert(array_merge(
            $this->rowsForStreetOutcode('BULWARK ROAD', 'B79', '2024-02-05 00:00:00', 'tx-bulwark'),
            $this->rowsForStreetOutcode('CHURCH LANE', 'B80', '2024-02-06 00:00:00', 'tx-church'),
            $this->rowsForStreetOutcode('MARKET STREET', 'B81', '2024-02-07 00:00:00', 'tx-market'),
            $this->rowsForStreetOutcode('RIVER WAY', 'B82', '2024-02-08 00:00:00', 'tx-river'),
            $this->rowsForStreetOutcode('STATION ROAD', 'B83', '2024-02-09 00:00:00', 'tx-station')
        ));

        File::put(
            public_path('sitemap-index.xml'),
            '<?xml version="1.0" encoding="UTF-8"?>'
            .'<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
            .'<sitemap><loc>'.url('/sitemap.xml').'</loc></sitemap>'
            .'</sitemapindex>'
        );

        $this->artisan('sitemap:generate-streets', ['--chunk-size' => 2])->assertExitCode(0);
        $this->artisan('sitemap:generate-streets', ['--chunk-size' => 2])->assertExitCode(0);

        $this->assertFileExists(public_path('sitemap-streets.xml'));
        $this->assertFileExists(public_path('sitemap-streets-1.xml'));
        $this->assertFileExists(public_path('sitemap-streets-2.xml'));
        $this->assertFileExists(public_path('sitemap-streets-3.xml'));
        $this->assertFileDoesNotExist(public_path('sitemap-streets-4.xml'));

        $indexXml = simplexml_load_file(public_path('sitemap-streets.xml'));
        $this->assertNotFalse($indexXml);
        $this->assertSame('sitemapindex', $indexXml->getName());

        $chunkOne = simplexml_load_file(public_path('sitemap-streets-1.xml'));
        $chunkTwo = simplexml_load_file(public_path('sitemap-streets-2.xml'));
        $chunkThree = simplexml_load_file(public_path('sitemap-streets-3.xml'));
        $this->assertNotFalse($chunkOne);
        $this->assertNotFalse($chunkTwo);
        $this->assertNotFalse($chunkThree);
        $this->assertCount(2, $chunkOne->url);
        $this->assertCount(2, $chunkTwo->url);
        $this->assertCount(1, $chunkThree->url);

        $masterIndex = simplexml_load_file(public_path('sitemap-index.xml'));
        $this->assertNotFalse($masterIndex);
        $this->assertSame('sitemapindex', $masterIndex->getName());
        $this->assertCount(4, $masterIndex->sitemap);

        $masterIndexXmlString = (string) File::get(public_path('sitemap-index.xml'));
        $this->assertSame(1, substr_count($masterIndexXmlString, url('/sitemap-streets-1.xml')));
        $this->assertSame(1, substr_count($masterIndexXmlString, url('/sitemap-streets-2.xml')));
        $this->assertSame(1, substr_count($masterIndexXmlString, url('/sitemap-streets-3.xml')));
    }

    public function test_street_sitemap_route_serves_xml_file(): void
    {
        File::put(
            public_path('sitemap-streets.xml'),
            '<?xml version="1.0" encoding="UTF-8"?><urlset></urlset>'
        );

        $response = $this->get('/sitemap-streets.xml');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/xml; charset=UTF-8');
    }

    public function test_street_sitemap_chunk_route_serves_xml_file(): void
    {
        File::put(
            public_path('sitemap-streets-1.xml'),
            '<?xml version="1.0" encoding="UTF-8"?><urlset></urlset>'
        );

        $response = $this->get('/sitemap-streets-1.xml');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/xml; charset=UTF-8');
    }

    private function ensureLandRegistryTable(): void
    {
        if (Schema::hasTable('land_registry')) {
            return;
        }

        Schema::create('land_registry', function (Blueprint $table): void {
            $table->char('TransactionID', 36)->nullable();
            $table->unsignedInteger('Price')->nullable();
            $table->dateTime('Date')->nullable();
            $table->string('Postcode', 10)->nullable();
            $table->enum('PropertyType', ['D', 'S', 'T', 'F', 'O'])->nullable();
            $table->enum('NewBuild', ['Y', 'N'])->nullable();
            $table->enum('Duration', ['F', 'L'])->nullable();
            $table->string('PAON', 100)->nullable();
            $table->string('SAON', 100)->nullable();
            $table->string('Street', 100)->nullable();
            $table->string('Locality', 100)->nullable();
            $table->string('TownCity', 100)->nullable();
            $table->string('District', 100)->nullable();
            $table->string('County', 100)->nullable();
            $table->enum('PPDCategoryType', ['A', 'B'])->nullable();
            $table->char('RecordStatus', 1)->nullable();
        });
    }

    /**
     * @return array<string, int|string|null>
     */
    private function saleRow(
        string $transactionId,
        string $street,
        string $postcode,
        string $date,
        string $category = 'A'
    ): array {
        return [
            'TransactionID' => $transactionId,
            'Price' => 250000,
            'Date' => $date,
            'Postcode' => $postcode,
            'PropertyType' => 'T',
            'NewBuild' => 'N',
            'Duration' => 'F',
            'PAON' => '1',
            'SAON' => null,
            'Street' => $street,
            'Locality' => null,
            'TownCity' => 'Tamworth',
            'District' => 'Lichfield',
            'County' => 'Staffordshire',
            'PPDCategoryType' => $category,
            'RecordStatus' => null,
        ];
    }

    /**
     * @return array<int, array<string, int|string|null>>
     */
    private function rowsForStreetOutcode(string $street, string $outcode, string $date, string $transactionPrefix): array
    {
        return collect(range(1, 5))
            ->map(fn (int $index): array => $this->saleRow(
                sprintf('%s-%02d', $transactionPrefix, $index),
                $street,
                sprintf('%s %dAA', $outcode, $index),
                $date
            ))
            ->all();
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
            ->filter(fn ($file) => preg_match('/^sitemap-streets-\d+\.xml$/', $file->getFilename()) === 1)
            ->each(fn ($file) => File::delete($file->getPathname()));
    }
}
