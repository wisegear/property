<?php

namespace App\Http\Controllers;

use App\Http\Requests\InsightsFilterRequest;
use App\Models\MarketInsight;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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
            'selectedGroup' => (string) $request->validated('group', ''),
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

        $latest = MarketInsight::query()
            ->selectRaw('MAX(latest.period_end)')
            ->from('market_insights as latest')
            ->whereColumn('latest.area_code', 'market_insights.area_code');

        $representatives = MarketInsight::query()
            ->selectRaw('MIN(id)')
            ->where('period_end', '=', $latest)
            ->groupBy('area_code');

        $query = MarketInsight::query()->whereIn('id', $representatives);
        $group = (string) $request->validated('group', '');
        $types = $type !== '' ? [$type] : (MarketInsight::SIGNAL_GROUPS[$group] ?? []);

        if ($types !== [] || $search !== '') {
            $query->whereExists(function ($matching) use ($types, $search, $likeOperator): void {
                $matching->selectRaw('1')->from('market_insights as matching')
                    ->whereColumn('matching.area_code', 'market_insights.area_code')
                    ->whereColumn('matching.period_end', 'market_insights.period_end');

                if ($types !== []) {
                    $matching->whereIn('matching.insight_type', $types);
                }

                if ($search !== '') {
                    $matching->where(function ($nested) use ($search, $likeOperator): void {
                        $normalized = strtoupper(str_replace(' ', '', $search));
                        $nested->whereRaw("UPPER(REPLACE(matching.area_code, ' ', '')) LIKE ?", ['%'.$normalized.'%'])
                            ->orWhere('matching.insight_text', $likeOperator, '%'.$search.'%');
                    });
                }
            });
        }

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
            default:
                if ($driver === 'pgsql') {
                    $query->orderByRaw("regexp_replace(area_code, '[0-9].*$', '') ASC");
                }

                $query->orderBy('area_code')
                    ->orderByDesc('period_end');
                break;
        }

        $areas = $query->paginate(self::PER_PAGE)->appends($request->query());
        $signals = MarketInsight::query()->whereIn('area_code', $areas->getCollection()->pluck('area_code'))
            ->orderByDesc('id')->get()->groupBy('area_code');

        foreach ($areas as $area) {
            $area->setRelation('latestSignals', $signals->get($area->area_code, collect())
                ->filter(fn (MarketInsight $signal): bool => $signal->period_end->equalTo($area->period_end))
                ->unique('insight_type')->values());
        }

        return $areas;
    }

    protected function sortOption(InsightsFilterRequest $request): string
    {
        $sort = (string) $request->query('sort', 'sector_asc');
        $allowed = [
            'sector_asc',
            'sector_desc',
            'transactions_desc',
            'transactions_asc',
        ];

        return in_array($sort, $allowed, true) ? $sort : 'sector_asc';
    }
}
