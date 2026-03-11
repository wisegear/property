<?php

namespace App\Http\Controllers;

use App\Http\Requests\InsightsFilterRequest;
use App\Models\MarketInsight;
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
            'demand_collapse' => 'Demand Collapse',
            'sector_outperformance' => 'Sector Outperformance',
            'momentum_reversal' => 'Momentum Reversal',
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

        return view('insights.index', [
            'query' => $query,
            'insightTypes' => $this->insightTypes(),
            'selectedType' => is_string($selectedType) ? $selectedType : '',
            'search' => $search,
        ]);
    }

    protected function filteredInsights(InsightsFilterRequest $request): LengthAwarePaginator
    {
        $search = trim((string) $request->validated('search', ''));
        $type = (string) $request->validated('type', '');
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

        if ($driver === 'pgsql') {
            $query->orderByRaw("regexp_replace(area_code, '[0-9].*$', '') ASC");
        }

        return $query
            ->orderBy('area_code')
            ->orderByDesc('period_end')
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }
}
