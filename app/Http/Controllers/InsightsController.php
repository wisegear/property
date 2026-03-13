<?php

namespace App\Http\Controllers;

use App\Http\Requests\InsightsFilterRequest;
use App\Models\MarketInsight;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class InsightsController extends Controller
{
    private const PER_PAGE = 20;

    /**
     * @return array<string, string>
     */
    protected function insightTypes(): array
    {
        return [
            'price_spike' => 'Price Spike',
            'price_collapse' => 'Price Collapse',
            'demand_collapse' => 'Demand Collapse',
            'liquidity_stress' => 'Liquidity Stress',
            'liquidity_surge' => 'Liquidity Surge',
            'market_freeze' => 'Market Freeze',
            'sector_outperformance' => 'Sector Outperformance',
            'momentum_reversal' => 'Momentum Reversal',
            'unexpected_hotspot' => 'Unexpected Hotspot',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function insightDescriptions(): array
    {
        return [
            'price_spike' => 'Median prices have risen unusually fast over the latest rolling 12-month period, which may indicate intense local demand or constrained supply.',
            'price_collapse' => 'Median prices have fallen sharply over the latest rolling 12-month period, which can point to weakening demand, repricing, or distressed local conditions.',
            'demand_collapse' => 'Transaction volumes have dropped hard compared with the prior year, suggesting buyers have pulled back or activity has stalled.',
            'liquidity_stress' => 'Transaction volumes have fallen sharply while prices continue rising, suggesting weakening market liquidity.',
            'liquidity_surge' => 'Transaction volumes have risen strongly compared with the prior year, showing a sudden increase in market activity.',
            'market_freeze' => 'Transaction volumes have fallen so far that the market may be freezing up, with far fewer homes successfully completing sales.',
            'sector_outperformance' => 'This postcode sector is outperforming the wider national market, with stronger local price growth than the UK benchmark.',
            'momentum_reversal' => 'Earlier strong price growth has turned into decline, which can signal that local market momentum is rolling over.',
            'unexpected_hotspot' => 'This postcode sector is rising much faster than the national average, suggesting unusually strong local demand or catch-up growth.',
        ];
    }

    public function index(InsightsFilterRequest $request): View
    {
        return $this->renderIndex($request);
    }

    public function search(InsightsFilterRequest $request): View
    {
        return $this->renderIndex($request);
    }

    protected function renderIndex(InsightsFilterRequest $request): View
    {
        $query = $this->filteredInsights($request);
        $selectedType = $request->validated('type');
        $search = trim((string) $request->validated('search', ''));
        $sort = $this->sortOption($request);
        $lastRunAt = MarketInsight::query()->max('created_at');

        return view('insights.index', [
            'query' => $query,
            'insightTypes' => $this->insightTypes(),
            'insightDescriptions' => $this->insightDescriptions(),
            'insightTypeCounts' => $this->insightTypeCounts($request),
            'lastRunAt' => $lastRunAt === null ? null : Carbon::parse($lastRunAt),
            'selectedType' => is_string($selectedType) ? $selectedType : '',
            'search' => $search,
            'sort' => $sort,
        ]);
    }

    protected function filteredInsights(InsightsFilterRequest $request): LengthAwarePaginator
    {
        $search = trim((string) $request->validated('search', ''));
        $type = (string) $request->validated('type', '');
        $sort = $this->sortOption($request);
        $driver = DB::getDriverName();
        $likeOperator = $driver === 'pgsql' ? 'ILIKE' : 'LIKE';

        $query = MarketInsight::query()
            ->when($type !== '', function (Builder $builder) use ($type) {
                $builder->where('insight_type', $type);
            })
            ->when($search !== '', function (Builder $builder) use ($search, $likeOperator) {
                $builder->where(function ($nestedQuery) use ($search, $likeOperator) {
                    $nestedQuery
                        ->where('area_code', $likeOperator, '%'.$search.'%')
                        ->orWhere('insight_text', $likeOperator, '%'.$search.'%');
                });
            });

        switch ($sort) {
            case 'transactions_desc':
                $query->orderByDesc('transactions')
                    ->orderBy('area_code');
                break;
            case 'transactions_asc':
                $query->orderBy('transactions')
                    ->orderBy('area_code');
                break;
            case 'sector_desc':
                if ($driver === 'pgsql') {
                    $query->orderByRaw("regexp_replace(area_code, '[0-9].*$', '') DESC");
                }

                $query->orderByDesc('area_code')
                    ->orderByDesc('period_end');
                break;
            case 'latest_period_desc':
                $query->orderByDesc('period_end')
                    ->orderBy('area_code');
                break;
            default:
                if ($driver === 'pgsql') {
                    $query->orderByRaw("regexp_replace(area_code, '[0-9].*$', '') ASC");
                }

                $query->orderBy('area_code')
                    ->orderByDesc('period_end');
                break;
        }

        return $query
            ->paginate(self::PER_PAGE)
            ->appends($request->query());
    }

    protected function sortOption(InsightsFilterRequest $request): string
    {
        $sort = (string) $request->query('sort', 'sector_asc');
        $allowed = [
            'sector_asc',
            'sector_desc',
            'transactions_desc',
            'transactions_asc',
            'latest_period_desc',
        ];

        return in_array($sort, $allowed, true) ? $sort : 'sector_asc';
    }

    /**
     * @return array<string, int>
     */
    protected function insightTypeCounts(InsightsFilterRequest $request): array
    {
        $search = trim((string) $request->validated('search', ''));
        $driver = DB::getDriverName();
        $likeOperator = $driver === 'pgsql' ? 'ILIKE' : 'LIKE';
        $counts = array_fill_keys(array_keys($this->insightTypes()), 0);

        $rows = MarketInsight::query()
            ->select('insight_type', DB::raw('COUNT(*) as aggregate'))
            ->when($search !== '', function (Builder $builder) use ($search, $likeOperator) {
                $builder->where(function ($nestedQuery) use ($search, $likeOperator) {
                    $nestedQuery
                        ->where('area_code', $likeOperator, '%'.$search.'%')
                        ->orWhere('insight_text', $likeOperator, '%'.$search.'%');
                });
            })
            ->groupBy('insight_type')
            ->get();

        foreach ($rows as $row) {
            $counts[(string) $row->insight_type] = (int) $row->aggregate;
        }

        return $counts;
    }
}
