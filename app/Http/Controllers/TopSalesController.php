<?php

namespace App\Http\Controllers;

use App\Services\TopSalesService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class TopSalesController extends Controller
{
    public function index(Request $request, TopSalesService $topSalesService): View
    {
        $mode = $topSalesService->normalizeMode((string) $request->string('mode', 'ultra'));
        $modeConfig = $topSalesService->modeConfig($mode);
        $modeOptions = collect(['ultra', 'london', 'rest'])
            ->mapWithKeys(fn (string $option): array => [$option => $topSalesService->modeConfig($option)]);
        $lastWarmedAt = Cache::get($topSalesService->lastWarmedCacheKey());
        $allSales = $topSalesService->cachedSales($mode);
        $topSale = $allSales->first();
        $topThree = $allSales
            ->reject(fn (object $sale): bool => ($sale->property_slug ?? null) === ($topSale->property_slug ?? null))
            ->take(3)
            ->values();
        $londonCount = $allSales->filter(
            fn (object $sale): bool => strtoupper((string) ($sale->County ?? '')) === 'GREATER LONDON'
        )->count();
        $perPage = 50;
        $currentPage = max(1, $request->integer('page', 1));
        $sales = new LengthAwarePaginator(
            $allSales->forPage($currentPage, $perPage)->values(),
            $allSales->count(),
            $perPage,
            $currentPage,
            [
                'path' => $request->url(),
                'query' => $request->query(),
                'pageName' => 'page',
            ]
        );
        $salesScatter = $sales->getCollection()
            ->filter(fn (object $sale): bool => isset($sale->Date, $sale->Price) && $sale->Date !== null && $sale->Price !== null)
            ->map(function (object $sale): array {
                $address = collect([
                    $sale->PAON ?? null,
                    $sale->SAON ?? null,
                    $sale->Street ?? null,
                ])->filter()->implode(', ');

                return [
                    'x' => (int) Carbon::parse((string) $sale->Date)->format('Y'),
                    'y' => (int) $sale->Price,
                    'address' => $address !== '' ? $address : (string) ($sale->Postcode ?? 'Unknown address'),
                    'date' => Carbon::parse((string) $sale->Date)->format('d M Y'),
                ];
            })
            ->values();
        $insight = match ($mode) {
            'london' => 'These sales show where London\'s prime market is still clearing at scale.',
            'rest' => 'These are the most expensive sales outside London, showing where wealth is concentrating beyond the capital.',
            default => $londonCount > ($allSales->count() * 0.7)
                ? 'Most top sales are concentrated in prime central London.'
                : 'High-value sales are appearing outside London, which is unusual.',
        };

        return view('pages.top-sales.index', [
            'sales' => $sales,
            'mode' => $mode,
            'modeConfig' => $modeConfig,
            'modeOptions' => $modeOptions,
            'lastWarmedAt' => is_string($lastWarmedAt) ? Carbon::parse($lastWarmedAt) : null,
            'topSale' => $topSale,
            'topThree' => $topThree,
            'salesScatter' => $salesScatter,
            'insight' => $insight,
        ]);
    }
}
