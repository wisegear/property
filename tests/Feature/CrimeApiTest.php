<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CrimeApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    }

    public function test_successful_national_response_contains_summary_and_chart(): void
    {
        $this->seedCrimeDashboardRows();

        $this->getJson(route('api.v1.insights.crime.index', absolute: false))
            ->assertOk()
            ->assertJsonPath('data.latest_month', '2026-03-01')
            ->assertJsonPath('data.latest_month_label', 'March 2026')
            ->assertJsonPath('data.summary.total_12m', 51)
            ->assertJsonPath('data.summary.prev_12m', 39)
            ->assertJsonPath('data.summary.pct_change', 30.8)
            ->assertJsonStructure([
                'data' => [
                    'latest_month',
                    'latest_month_label',
                    'summary' => [
                        'total_12m',
                        'prev_12m',
                        'pct_change',
                        'last_3m_total',
                        'prev_3m_total',
                        'last_3m_change',
                    ],
                    'chart' => ['labels', 'current_year', 'previous_year'],
                    'crime_types',
                    'drivers',
                    'areas',
                    'website_url',
                ],
            ])
            ->assertJsonCount(12, 'data.chart.labels')
            ->assertJsonPath('data.chart.labels.0', 'Apr')
            ->assertJsonPath('data.chart.current_year.11', 5)
            ->assertJsonPath('data.chart.previous_year.11', 3)
            ->assertJsonPath('data.website_url', route('insights.crime.index'));
    }

    public function test_national_response_contains_crime_types_and_drivers(): void
    {
        $this->seedCrimeDashboardRows();

        $this->getJson(route('api.v1.insights.crime.index', absolute: false))
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'crime_types' => [
                        '*' => [
                            'type',
                            'total_12m',
                            'total_prev_12m',
                            'yoy_change',
                            'share_pct',
                            'trend',
                        ],
                    ],
                    'drivers' => [
                        'overall_yoy',
                        'increases' => [
                            '*' => ['type', 'impact', 'yoy_change'],
                        ],
                        'decreases',
                    ],
                ],
            ])
            ->assertJsonPath('data.crime_types.0.type', 'Theft')
            ->assertJsonPath('data.drivers.overall_yoy', 30.8)
            ->assertJsonPath('data.drivers.increases.0.type', 'Burglary')
            ->assertJsonPath('data.drivers.increases.0.impact', 9);
    }

    public function test_national_area_summaries_contain_api_and_website_urls(): void
    {
        $this->seedCrimeDashboardRows();

        $this->getJson(route('api.v1.insights.crime.index', absolute: false))
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'areas' => [
                        '*' => [
                            'area',
                            'slug',
                            'total_12m',
                            'prev_12m',
                            'pct_change',
                            'trend',
                            'api_url',
                            'website_url',
                        ],
                    ],
                ],
            ])
            ->assertJsonPath(
                'data.areas.0.api_url',
                route('api.v1.insights.crime.show', ['area_slug' => 'alpha-county']),
            )
            ->assertJsonPath(
                'data.areas.0.website_url',
                route('insights.crime.show', ['area' => 'alpha-county']),
            );
    }

    public function test_successful_regional_response_contains_expected_data(): void
    {
        $this->seedCrimeDashboardRows();

        $this->getJson(route('api.v1.insights.crime.show', [
            'area_slug' => 'alpha-county',
        ], absolute: false))
            ->assertOk()
            ->assertJsonPath('data.area', 'Alpha County')
            ->assertJsonPath('data.area_slug', 'alpha-county')
            ->assertJsonPath('data.summary.total_12m', 36)
            ->assertJsonPath('data.crime_breakdown.0.type', 'Burglary')
            ->assertJsonPath('data.crime_breakdown.0.national_yoy', 300)
            ->assertJsonPath('data.crime_breakdown.1.is_largest', true)
            ->assertJsonPath(
                'data.website_url',
                route('insights.crime.show', ['area' => 'alpha-county']),
            )
            ->assertJsonStructure([
                'data' => [
                    'area',
                    'area_slug',
                    'latest_month',
                    'latest_month_label',
                    'summary',
                    'chart',
                    'crime_breakdown' => [
                        '*' => [
                            'type',
                            'total_12m',
                            'total_prev_12m',
                            'yoy_change',
                            'share_pct',
                            'impact',
                            'trend',
                            'national_yoy',
                            'is_largest',
                        ],
                    ],
                    'drivers',
                    'website_url',
                ],
            ]);
    }

    public function test_missing_regional_area_returns_not_found(): void
    {
        $this->seedCrimeDashboardRows();

        $this->getJson(route('api.v1.insights.crime.show', [
            'area_slug' => 'unknown-area',
        ], absolute: false))->assertNotFound();
    }

    public function test_empty_crime_dataset_returns_an_empty_national_response(): void
    {
        $this->getJson(route('api.v1.insights.crime.index', absolute: false))
            ->assertOk()
            ->assertJsonPath('data.latest_month', null)
            ->assertJsonPath('data.latest_month_label', null)
            ->assertJsonPath('data.summary.total_12m', 0)
            ->assertJsonPath('data.summary.pct_change', 0)
            ->assertJsonCount(0, 'data.chart.labels')
            ->assertJsonCount(0, 'data.crime_types')
            ->assertJsonCount(0, 'data.drivers.increases')
            ->assertJsonCount(0, 'data.areas');
    }

    private function seedCrimeDashboardRows(): void
    {
        $rows = [];

        foreach (range(0, 11) as $offset) {
            $month = now()->setDate(2024, 4, 1)->addMonths($offset)->toDateString();

            $rows = [
                ...$rows,
                ...$this->repeatCrimeRows(2, $month, 'Alpha County', 'Theft'),
                ...($offset < 3 ? $this->repeatCrimeRows(1, $month, 'Alpha County', 'Burglary') : []),
                ...$this->repeatCrimeRows(1, $month, 'Beta Region', 'Vehicle crime'),
            ];
        }

        foreach (range(0, 11) as $offset) {
            $month = now()->setDate(2025, 4, 1)->addMonths($offset)->toDateString();

            $rows = [
                ...$rows,
                ...$this->repeatCrimeRows(2, $month, 'Alpha County', 'Theft'),
                ...$this->repeatCrimeRows(1, $month, 'Alpha County', 'Burglary'),
                ...$this->repeatCrimeRows(1, $month, 'Beta Region', 'Vehicle crime'),
                ...($offset >= 9 ? $this->repeatCrimeRows(1, $month, 'Beta Region', 'Robbery') : []),
            ];
        }

        DB::table('crime')->insert($rows);
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function repeatCrimeRows(int $count, string $month, string $area, string $crimeType): array
    {
        $rows = [];

        foreach (range(1, $count) as $index) {
            $rows[] = [
                'crime_id' => md5($month.$area.$crimeType.$index.microtime(true)),
                'month' => $month,
                'falls_within' => $area,
                'reported_by' => $area.' Police',
                'crime_type' => $crimeType,
            ];
        }

        return $rows;
    }
}
