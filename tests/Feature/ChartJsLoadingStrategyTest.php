<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ChartJsLoadingStrategyTest extends TestCase
{
    public function test_shared_layout_does_not_load_chart_js_globally(): void
    {
        $layoutContents = File::get(resource_path('views/layouts/app.blade.php'));

        $this->assertStringNotContainsString(
            'https://cdn.jsdelivr.net/npm/chart.js@4.5.0/dist/chart.umd.min.js',
            $layoutContents
        );
    }

    public function test_chart_pages_opt_in_to_chart_js_loading(): void
    {
        $chartViews = [
            'pages/top-sales/index.blade.php',
            'arrears/index.blade.php',
            'property/home.blade.php',
            'property/area-show.blade.php',
            'mortgagecalc/index.blade.php',
            'local_authority/scotland.blade.php',
            'local_authority/england.blade.php',
            'epc/search_scotland.blade.php',
            'rental/index.blade.php',
            'rental/partials/nation.blade.php',
            'insights/show.blade.php',
            'insights/index.blade.php',
            'insights/dashboard.blade.php',
            'insights/crime/index.blade.php',
            'insights/crime/show.blade.php',
        ];

        foreach ($chartViews as $chartView) {
            $contents = File::get(resource_path('views/'.$chartView));

            $this->assertStringContainsString(
                "@include('partials.chartjs-head')",
                $contents,
                $chartView
            );
        }
    }
}
