<?php

namespace App\Http\Controllers;

use App\Models\BlogPosts;
use App\Models\MarketInsight;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * PagesController
 *
 * Responsible for the public pages (home/about). The home action can:
 *  - Perform a postcode search with sorting + pagination
 *  - Compute/cached site-wide aggregates for charts (sales counts, avg prices, prime/ultra prime slices)
 */
class PagesController extends Controller
{
    public function home(): View
    {
        // Get the 4 most recent blog posts
        $posts = BlogPosts::where('published', true)->orderBy('date', 'desc')->take(4)->get();

        // Get stats from cache only (warmed by home:stats-warm command)
        $stats = Cache::get('homepage_stats', [
            'property_records' => 0,
            'uk_avg_price' => 0,
            'uk_avg_rent' => 0,
            'bank_rate' => 0,
            'inflation_rate' => 0,
            'epc_count' => 0,
        ]);

        $totalStress = Cache::get('eco:total_stress');
        if (is_null($totalStress)) {
            $totalStress = Cache::get('eco:total_stress_persist');
        }

        return view('pages.home', [
            'posts' => $posts,
            'stats' => $stats,
            'totalStress' => $totalStress,
            ...$this->marketInsightsSummary(),
        ]);
    }

    /**
     * Static About page.
     */
    public function about(): View
    {
        return view('pages.about');
    }

    /**
     * @return array{marketInsightsCount:int, marketInsightsLastRunAt:?Carbon, marketInsightSignalCount:int}
     */
    private function marketInsightsSummary(): array
    {
        if (! Schema::hasTable('market_insights')) {
            return [
                'marketInsightsCount' => 0,
                'marketInsightsLastRunAt' => null,
                'marketInsightSignalCount' => 9,
            ];
        }

        $marketInsightsCount = MarketInsight::query()->count();
        $marketInsightsLastRunAt = MarketInsight::query()->max('created_at');

        return [
            'marketInsightsCount' => $marketInsightsCount,
            'marketInsightsLastRunAt' => $marketInsightsLastRunAt === null ? null : Carbon::parse($marketInsightsLastRunAt),
            'marketInsightSignalCount' => 9,
        ];
    }
}
