<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EpcPostcodeRouteTest extends TestCase
{
    private ?string $originalPostcodeIndex = null;

    protected function setUp(): void
    {
        parent::setUp();

        $path = public_path('data/epc-postcodes.json');
        $this->originalPostcodeIndex = File::exists($path) ? File::get($path) : null;
    }

    protected function tearDown(): void
    {
        $path = public_path('data/epc-postcodes.json');
        File::ensureDirectoryExists(dirname($path));

        if ($this->originalPostcodeIndex === null) {
            if (File::exists($path)) {
                File::delete($path);
            }
        } else {
            File::put($path, $this->originalPostcodeIndex);
        }

        parent::tearDown();
    }

    public function test_england_wales_postcode_route_renders_postcode_view_when_postcode_exists(): void
    {
        Cache::flush();
        $this->writePostcodeIndex([
            'england_wales' => ['WR5 3EU'],
            'scotland' => ['KA7 3XY'],
        ]);

        $response = $this->get('/epc/postcode/WR5-3EU');

        $response->assertOk();
        $response->assertSee('WR5 3EU');
        $response->assertSee('England & Wales');
        $cached = Cache::get('epc:v5:england_wales:postcode:WR5 3EU');
        $this->assertIsArray($cached);
        $this->assertSame('WR5 3EU', $cached['postcode']);
        $this->assertSame('england_wales', $cached['regime']);
    }

    public function test_england_wales_cached_payload_includes_aggregated_stats(): void
    {
        Cache::flush();
        $this->ensureEnglandWalesEpcSchema();
        DB::table('epc_certificates')->delete();
        DB::table('epc_certificates')->insert([
            [
                'POSTCODE' => 'WR5 3EU',
                'INSPECTION_DATE' => '2016-01-10',
                'CURRENT_ENERGY_RATING' => 'C',
                'POTENTIAL_ENERGY_RATING' => 'B',
                'ENVIRONMENT_IMPACT_CURRENT' => '93',
                'ENVIRONMENT_IMPACT_POTENTIAL' => '95',
                'CURRENT_ENERGY_EFFICIENCY' => '60',
                'LMK_KEY' => 'LMK-1',
                'ADDRESS' => '1 Test Street',
            ],
            [
                'POSTCODE' => 'WR5 3EU',
                'INSPECTION_DATE' => '2017-03-12',
                'CURRENT_ENERGY_RATING' => 'D',
                'POTENTIAL_ENERGY_RATING' => 'C',
                'ENVIRONMENT_IMPACT_CURRENT' => '82',
                'ENVIRONMENT_IMPACT_POTENTIAL' => '84',
                'CURRENT_ENERGY_EFFICIENCY' => '70',
                'LMK_KEY' => 'LMK-2',
                'ADDRESS' => '2 Test Street',
            ],
            [
                'POSTCODE' => 'WR5 3EU',
                'INSPECTION_DATE' => '2020-08-19',
                'CURRENT_ENERGY_RATING' => 'A',
                'POTENTIAL_ENERGY_RATING' => 'A',
                'ENVIRONMENT_IMPACT_CURRENT' => '70',
                'ENVIRONMENT_IMPACT_POTENTIAL' => '73',
                'CURRENT_ENERGY_EFFICIENCY' => '80',
                'LMK_KEY' => 'LMK-3',
                'ADDRESS' => '3 Test Street',
            ],
            [
                'POSTCODE' => 'WR5 3EU',
                'INSPECTION_DATE' => '2014-12-31',
                'CURRENT_ENERGY_RATING' => 'B',
                'POTENTIAL_ENERGY_RATING' => 'A',
                'ENVIRONMENT_IMPACT_CURRENT' => '99',
                'ENVIRONMENT_IMPACT_POTENTIAL' => '99',
                'CURRENT_ENERGY_EFFICIENCY' => '99',
                'LMK_KEY' => 'LMK-4',
                'ADDRESS' => '4 Legacy Street',
            ],
        ]);

        $this->writePostcodeIndex([
            'england_wales' => ['WR5 3EU'],
            'scotland' => ['KA7 3XY'],
        ]);

        $this->get('/epc/postcode/WR5-3EU')->assertOk();

        $cached = Cache::get('epc:v5:england_wales:postcode:WR5 3EU');
        $this->assertIsArray($cached);
        $this->assertSame(3, $cached['england_wales']['total_certificates']);
        $this->assertSame(1, $cached['england_wales']['rating_distribution']['A']);
        $this->assertSame(1, $cached['england_wales']['rating_distribution']['C']);
        $this->assertSame(1, $cached['england_wales']['rating_distribution']['D']);
        $this->assertSame(1, $cached['england_wales']['potential_rating_distribution']['A']);
        $this->assertSame(1, $cached['england_wales']['potential_rating_distribution']['B']);
        $this->assertSame(1, $cached['england_wales']['potential_rating_distribution']['C']);
        $this->assertSame(1, $cached['england_wales']['environment_rating_distribution']['A']);
        $this->assertSame(1, $cached['england_wales']['environment_rating_distribution']['B']);
        $this->assertSame(1, $cached['england_wales']['environment_rating_distribution']['C']);
        $this->assertSame(1, $cached['england_wales']['potential_environment_rating_distribution']['A']);
        $this->assertSame(1, $cached['england_wales']['potential_environment_rating_distribution']['B']);
        $this->assertSame(1, $cached['england_wales']['potential_environment_rating_distribution']['C']);
        $this->assertSame('2016-01-10', $cached['england_wales']['inspection_dates']['earliest']);
        $this->assertSame('2020-08-19', $cached['england_wales']['inspection_dates']['latest']);
        $this->assertCount(3, $cached['certificates']);
        $this->assertSame('LMK-3', $cached['certificates'][0]['identifier']);
        $this->assertSame('3 Test Street', $cached['certificates'][0]['address']);
        $this->assertSame('A', $cached['certificates'][0]['rating']);
        $this->assertSame('A', $cached['certificates'][0]['potential_rating']);
        $this->assertSame('2020-08-19', $cached['certificates'][0]['inspection_date']);
        $this->assertSame('/epc/LMK-3', $cached['certificates'][0]['url']);
    }

    public function test_scotland_postcode_route_renders_postcode_view_when_postcode_exists(): void
    {
        Cache::flush();
        $this->writePostcodeIndex([
            'england_wales' => ['WR5 3EU'],
            'scotland' => ['KA7 3XY'],
        ]);

        $response = $this->get('/epc/scotland/postcode/KA7-3XY');

        $response->assertOk();
        $response->assertSee('KA7 3XY');
        $response->assertSee('scotland');
    }

    public function test_scotland_cached_payload_includes_aggregated_stats(): void
    {
        Cache::flush();
        $this->ensureScotlandEpcSchema();
        DB::table('epc_certificates_scotland')->delete();
        DB::table('epc_certificates_scotland')->insert([
            [
                'POSTCODE' => 'KA7 3XY',
                'INSPECTION_DATE' => '2015-02-01',
                'CURRENT_ENERGY_RATING' => 'B',
                'POTENTIAL_ENERGY_RATING' => 'A',
                'CURRENT_ENVIRONMENTAL_RATING' => 'D',
                'POTENTIAL_ENVIRONMENTAL_RATING' => 'C',
                'REPORT_REFERENCE_NUMBER' => 'RRN-1',
                'ADDRESS1' => '1 Scot Street',
                'ADDRESS2' => 'AYR',
            ],
            [
                'POSTCODE' => 'KA7 3XY',
                'INSPECTION_DATE' => '2019-07-15',
                'CURRENT_ENERGY_RATING' => 'C',
                'POTENTIAL_ENERGY_RATING' => 'B',
                'CURRENT_ENVIRONMENTAL_RATING' => 'C',
                'POTENTIAL_ENVIRONMENTAL_RATING' => 'B',
                'REPORT_REFERENCE_NUMBER' => 'RRN-2',
                'ADDRESS1' => '2 Scot Street',
                'ADDRESS2' => null,
            ],
            [
                'POSTCODE' => 'KA7 3XY',
                'INSPECTION_DATE' => '2021-11-30',
                'CURRENT_ENERGY_RATING' => 'C',
                'POTENTIAL_ENERGY_RATING' => 'B',
                'CURRENT_ENVIRONMENTAL_RATING' => 'C',
                'POTENTIAL_ENVIRONMENTAL_RATING' => 'B',
                'REPORT_REFERENCE_NUMBER' => 'RRN-3',
                'ADDRESS1' => '3 Scot Street',
                'ADDRESS2' => null,
            ],
            [
                'POSTCODE' => 'KA7 3XY',
                'INSPECTION_DATE' => '2014-12-31',
                'CURRENT_ENERGY_RATING' => 'A',
                'POTENTIAL_ENERGY_RATING' => 'A',
                'CURRENT_ENVIRONMENTAL_RATING' => 'A',
                'POTENTIAL_ENVIRONMENTAL_RATING' => 'A',
                'REPORT_REFERENCE_NUMBER' => 'RRN-4',
                'ADDRESS1' => '4 Legacy Street',
                'ADDRESS2' => null,
            ],
        ]);

        $this->writePostcodeIndex([
            'england_wales' => ['WR5 3EU'],
            'scotland' => ['KA7 3XY'],
        ]);

        $this->get('/epc/scotland/postcode/KA7-3XY')->assertOk();

        $cached = Cache::get('epc:v5:scotland:postcode:KA7 3XY');
        $this->assertIsArray($cached);
        $this->assertSame(3, $cached['scotland']['total_certificates']);
        $this->assertSame(1, $cached['scotland']['rating_distribution']['B']);
        $this->assertSame(2, $cached['scotland']['rating_distribution']['C']);
        $this->assertSame(1, $cached['scotland']['potential_rating_distribution']['A']);
        $this->assertSame(2, $cached['scotland']['potential_rating_distribution']['B']);
        $this->assertSame(1, $cached['scotland']['environment_rating_distribution']['D']);
        $this->assertSame(2, $cached['scotland']['environment_rating_distribution']['C']);
        $this->assertSame(1, $cached['scotland']['potential_environment_rating_distribution']['C']);
        $this->assertSame(2, $cached['scotland']['potential_environment_rating_distribution']['B']);
        $this->assertSame('2015-02-01', $cached['scotland']['inspection_dates']['earliest']);
        $this->assertSame('2021-11-30', $cached['scotland']['inspection_dates']['latest']);
        $this->assertCount(3, $cached['certificates']);
        $this->assertSame('RRN-3', $cached['certificates'][0]['identifier']);
        $this->assertSame('3 Scot Street', $cached['certificates'][0]['address']);
        $this->assertSame('C', $cached['certificates'][0]['rating']);
        $this->assertSame('B', $cached['certificates'][0]['potential_rating']);
        $this->assertSame('2021-11-30', $cached['certificates'][0]['inspection_date']);
        $this->assertSame('/epc/scotland/RRN-3', $cached['certificates'][0]['url']);
    }

    public function test_certificate_list_includes_all_matching_rows_without_pagination_limit(): void
    {
        Cache::flush();
        $this->ensureEnglandWalesEpcSchema();
        DB::table('epc_certificates')->delete();

        $rows = [];
        for ($i = 1; $i <= 25; $i++) {
            $rows[] = [
                'POSTCODE' => 'WR5 3EU',
                'INSPECTION_DATE' => sprintf('2023-01-%02d', $i <= 28 ? $i : 28),
                'CURRENT_ENERGY_RATING' => 'C',
                'POTENTIAL_ENERGY_RATING' => 'B',
                'ENVIRONMENT_IMPACT_CURRENT' => '72',
                'ENVIRONMENT_IMPACT_POTENTIAL' => '80',
                'CURRENT_ENERGY_EFFICIENCY' => '70',
                'LMK_KEY' => 'LMK-'.$i,
                'ADDRESS' => $i.' Long List Street',
            ];
        }
        DB::table('epc_certificates')->insert($rows);

        $this->writePostcodeIndex([
            'england_wales' => ['WR5 3EU'],
            'scotland' => ['KA7 3XY'],
        ]);

        $this->get('/epc/postcode/WR5-3EU')->assertOk();
        $cached = Cache::get('epc:v5:england_wales:postcode:WR5 3EU');

        $this->assertCount(25, $cached['certificates']);
        $this->assertSame('LMK-25', $cached['certificates'][0]['identifier']);
        $this->assertSame('LMK-1', $cached['certificates'][24]['identifier']);
    }

    public function test_postcode_route_returns_not_found_when_postcode_is_not_indexed(): void
    {
        Cache::flush();
        $this->writePostcodeIndex([
            'england_wales' => ['WR5 3EU'],
            'scotland' => ['KA7 3XY'],
        ]);

        $response = $this->get('/epc/postcode/SW1A-1AA');

        $response->assertStatus(404);
        $this->assertNull(Cache::get('epc:v5:england_wales:postcode:SW1A 1AA'));
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

    private function ensureEnglandWalesEpcSchema(): void
    {
        if (! Schema::hasTable('epc_certificates')) {
            Schema::create('epc_certificates', function (Blueprint $table): void {
                $table->string('POSTCODE', 16)->nullable();
                $table->string('INSPECTION_DATE', 32)->nullable();
                $table->string('CURRENT_ENERGY_RATING', 8)->nullable();
                $table->string('POTENTIAL_ENERGY_RATING', 8)->nullable();
                $table->string('ENVIRONMENT_IMPACT_CURRENT', 32)->nullable();
                $table->string('ENVIRONMENT_IMPACT_POTENTIAL', 32)->nullable();
                $table->string('CURRENT_ENERGY_EFFICIENCY', 32)->nullable();
                $table->string('LMK_KEY', 128)->nullable();
                $table->text('ADDRESS')->nullable();
            });
        }

        foreach ([
            'POSTCODE' => fn (Blueprint $table) => $table->string('POSTCODE', 16)->nullable(),
            'INSPECTION_DATE' => fn (Blueprint $table) => $table->string('INSPECTION_DATE', 32)->nullable(),
            'CURRENT_ENERGY_RATING' => fn (Blueprint $table) => $table->string('CURRENT_ENERGY_RATING', 8)->nullable(),
            'POTENTIAL_ENERGY_RATING' => fn (Blueprint $table) => $table->string('POTENTIAL_ENERGY_RATING', 8)->nullable(),
            'ENVIRONMENT_IMPACT_CURRENT' => fn (Blueprint $table) => $table->string('ENVIRONMENT_IMPACT_CURRENT', 32)->nullable(),
            'ENVIRONMENT_IMPACT_POTENTIAL' => fn (Blueprint $table) => $table->string('ENVIRONMENT_IMPACT_POTENTIAL', 32)->nullable(),
            'CURRENT_ENERGY_EFFICIENCY' => fn (Blueprint $table) => $table->string('CURRENT_ENERGY_EFFICIENCY', 32)->nullable(),
            'LMK_KEY' => fn (Blueprint $table) => $table->string('LMK_KEY', 128)->nullable(),
            'ADDRESS' => fn (Blueprint $table) => $table->text('ADDRESS')->nullable(),
        ] as $column => $definition) {
            if (! Schema::hasColumn('epc_certificates', $column)) {
                Schema::table('epc_certificates', function (Blueprint $table) use ($definition): void {
                    $definition($table);
                });
            }
        }
    }

    private function ensureScotlandEpcSchema(): void
    {
        if (! Schema::hasTable('epc_certificates_scotland')) {
            Schema::create('epc_certificates_scotland', function (Blueprint $table): void {
                $table->string('POSTCODE', 16)->nullable();
                $table->string('INSPECTION_DATE', 32)->nullable();
                $table->string('CURRENT_ENERGY_RATING', 8)->nullable();
                $table->string('POTENTIAL_ENERGY_RATING', 8)->nullable();
                $table->string('CURRENT_ENVIRONMENTAL_RATING', 8)->nullable();
                $table->string('POTENTIAL_ENVIRONMENTAL_RATING', 8)->nullable();
                $table->string('ENVIRONMENT_IMPACT_CURRENT', 32)->nullable();
                $table->string('ENVIRONMENT_IMPACT_POTENTIAL', 32)->nullable();
                $table->string('REPORT_REFERENCE_NUMBER', 128)->nullable();
                $table->text('ADDRESS1')->nullable();
                $table->text('ADDRESS2')->nullable();
                $table->text('ADDRESS3')->nullable();
            });
        }

        foreach ([
            'POSTCODE' => fn (Blueprint $table) => $table->string('POSTCODE', 16)->nullable(),
            'INSPECTION_DATE' => fn (Blueprint $table) => $table->string('INSPECTION_DATE', 32)->nullable(),
            'CURRENT_ENERGY_RATING' => fn (Blueprint $table) => $table->string('CURRENT_ENERGY_RATING', 8)->nullable(),
            'POTENTIAL_ENERGY_RATING' => fn (Blueprint $table) => $table->string('POTENTIAL_ENERGY_RATING', 8)->nullable(),
            'CURRENT_ENVIRONMENTAL_RATING' => fn (Blueprint $table) => $table->string('CURRENT_ENVIRONMENTAL_RATING', 8)->nullable(),
            'POTENTIAL_ENVIRONMENTAL_RATING' => fn (Blueprint $table) => $table->string('POTENTIAL_ENVIRONMENTAL_RATING', 8)->nullable(),
            'ENVIRONMENT_IMPACT_CURRENT' => fn (Blueprint $table) => $table->string('ENVIRONMENT_IMPACT_CURRENT', 32)->nullable(),
            'ENVIRONMENT_IMPACT_POTENTIAL' => fn (Blueprint $table) => $table->string('ENVIRONMENT_IMPACT_POTENTIAL', 32)->nullable(),
            'REPORT_REFERENCE_NUMBER' => fn (Blueprint $table) => $table->string('REPORT_REFERENCE_NUMBER', 128)->nullable(),
            'ADDRESS1' => fn (Blueprint $table) => $table->text('ADDRESS1')->nullable(),
            'ADDRESS2' => fn (Blueprint $table) => $table->text('ADDRESS2')->nullable(),
            'ADDRESS3' => fn (Blueprint $table) => $table->text('ADDRESS3')->nullable(),
        ] as $column => $definition) {
            if (! Schema::hasColumn('epc_certificates_scotland', $column)) {
                Schema::table('epc_certificates_scotland', function (Blueprint $table) use ($definition): void {
                    $definition($table);
                });
            }
        }
    }
}
