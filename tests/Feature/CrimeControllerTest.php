<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CrimeControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    }

    public function test_national_crime_dashboard_renders_expected_metrics_and_navigation(): void
    {
        $this->seedCrimeDashboardRows();

        $response = $this->get(route('insights.crime.index', absolute: false));

        $response->assertOk();
        $response->assertViewIs('insights.crime.index');
        $response->assertSee('National Crime Dashboard');
        $response->assertSee('Crime volume by month');
        $response->assertSee('Monthly crime compared to the same period last year');
        $response->assertSee('Crime composition and trends');
        $response->assertSee('Regional Drilldowns');
        $response->assertSee('Key Headlines');
        $response->assertSee('href="'.route('insights.crime.index').'"', false);
        $response->assertSee('Crime', false);
        $response->assertSee('stroke="#22c55e"', false);
        $response->assertSee('stroke="#ef4444"', false);
        $response->assertViewHas('summary', function (array $summary): bool {
            return $summary['total_12m'] === 51
                && $summary['prev_12m'] === 39
                && $summary['pct_change'] === 30.8
                && $summary['last_3m_total'] === 15
                && $summary['prev_3m_total'] === 12
                && $summary['last_3m_change'] === 25.0;
        });
        $response->assertViewHas('areas', function (array $areas): bool {
            $alpha = collect($areas)->firstWhere('area', 'Alpha County');
            $beta = collect($areas)->firstWhere('area', 'Beta Region');

            return $alpha !== null
                && $beta !== null
                && $alpha['slug'] === 'alpha-county'
                && $alpha['total_12m'] === 36
                && $alpha['prev_12m'] === 27
                && $alpha['pct_change'] === 33.3
                && $beta['total_12m'] === 15
                && $beta['prev_12m'] === 12
                && $beta['pct_change'] === 25.0;
        });
        $response->assertViewHas('type_breakdown', function (array $types): bool {
            $topType = $types[0] ?? null;

            return $topType !== null
                && $topType['crime_type'] === 'Burglary'
                && $topType['total_12m'] === 12
                && $topType['prev_12m'] === 3
                && $topType['pct_change'] === 300.0;
        });
        $response->assertViewHas('crime_types', function (array $crimeTypes): bool {
            $first = $crimeTypes[0] ?? null;
            $last = $crimeTypes[3] ?? null;

            return count($crimeTypes) === 4
                && $first !== null
                && $first['type'] === 'Theft'
                && $first['total_12m'] === 24
                && $first['total_prev_12m'] === 24
                && $first['yoy_change'] === 0.0
                && $first['share_pct'] === 47.1
                && $first['trend'] === 'Flat'
                && $last !== null
                && $last['type'] === 'Robbery'
                && $last['trend'] === 'Up';
        });
        $response->assertViewHas('chart', function (array $chart): bool {
            return count($chart['labels']) === 12
                && $chart['labels'][0] === 'Apr'
                && $chart['labels'][11] === 'Mar'
                && $chart['current_year'][0] === 4
                && $chart['current_year'][11] === 5
                && $chart['previous_year'][0] === 4
                && $chart['previous_year'][11] === 3;
        });
        $response->assertViewHas('headlines', function (array $headlines): bool {
            return count($headlines) === 3
                && $headlines[0] === 'Crime up 30.8% nationally over the latest 12 months.'
                && $headlines[1] === 'Burglary is increasing fastest at 300.0%.'
                && $headlines[2] === 'Alpha County recorded the highest 12-month total with 36 crimes.';
        });
    }

    public function test_area_crime_dashboard_renders_breakdown_for_selected_area(): void
    {
        $this->seedCrimeDashboardRows();

        $response = $this->get(route('insights.crime.show', ['area' => 'alpha-county'], absolute: false));

        $response->assertOk();
        $response->assertViewIs('insights.crime.show');
        $response->assertSee('Alpha County Crime Drilldown');
        $response->assertSee('How crime is changing and what is driving it in Alpha County.');
        $response->assertSee('Monthly crime compared to the same period last year');
        $response->assertSee('driving change');
        $response->assertSee('Latest 12 months by type');
        $response->assertSee('Share of total crime (%)');
        $response->assertViewHas('area', 'Alpha County');
        $response->assertViewHas('drivers', function (array $drivers): bool {
            return $drivers['overall_yoy'] === 33.3
                && count($drivers['increases']) === 1
                && $drivers['increases'][0]['type'] === 'Burglary'
                && $drivers['increases'][0]['impact'] === 9
                && $drivers['increases'][0]['yoy_change'] === 300.0
                && $drivers['decreases'] === [];
        });
        $response->assertViewHas('summary', function (array $summary): bool {
            return $summary['total_12m'] === 36
                && $summary['prev_12m'] === 27
                && $summary['pct_change'] === 33.3
                && $summary['last_3m_total'] === 9
                && $summary['prev_3m_total'] === 9
                && $summary['last_3m_change'] === 0.0;
        });
        $response->assertViewHas('crime_breakdown', function (array $types): bool {
            return $types === [
                [
                    'type' => 'Burglary',
                    'total_12m' => 12,
                    'total_prev_12m' => 3,
                    'yoy_change' => 300.0,
                    'share_pct' => 33.3,
                    'impact' => 9,
                    'trend' => 'Up',
                    'national_yoy' => 300.0,
                    'is_largest' => false,
                ],
                [
                    'type' => 'Theft',
                    'total_12m' => 24,
                    'total_prev_12m' => 24,
                    'yoy_change' => 0.0,
                    'share_pct' => 66.7,
                    'impact' => 0,
                    'trend' => 'Flat',
                    'national_yoy' => 0.0,
                    'is_largest' => true,
                ],
            ];
        });
        $response->assertViewHas('chart', function (array $chart): bool {
            return $chart['labels'][0] === 'Apr'
                && $chart['labels'][11] === 'Mar'
                && $chart['current_year'][0] === 3
                && $chart['current_year'][11] === 3
                && $chart['previous_year'][0] === 3
                && $chart['previous_year'][11] === 2;
        });
    }

    public function test_unknown_area_returns_not_found(): void
    {
        $this->seedCrimeDashboardRows();

        $this->get(route('insights.crime.show', ['area' => 'unknown-area'], absolute: false))
            ->assertNotFound();
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
