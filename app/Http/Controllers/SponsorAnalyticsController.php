<?php

namespace App\Http\Controllers;

use App\Services\AnalyticsService;
use Illuminate\Contracts\View\View;

class SponsorAnalyticsController extends Controller
{
    public function __construct(
        private AnalyticsService $analyticsService
    ) {}

    public function index(): View
    {
        return view('pages.sponsor-analytics', [
            'stats' => $this->analyticsService->getSponsorDashboardStats(30),
        ]);
    }
}
