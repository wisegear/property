<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ChartJsCdnVersionTest extends TestCase
{
    public function test_chart_js_cdn_references_are_versioned(): void
    {
        $viewFiles = File::allFiles(resource_path('views'));

        foreach ($viewFiles as $viewFile) {
            $contents = File::get($viewFile->getPathname());

            $this->assertStringNotContainsString(
                'https://cdn.jsdelivr.net/npm/chart.js"',
                $contents,
                $viewFile->getRelativePathname()
            );

            $this->assertStringNotContainsString(
                "https://cdn.jsdelivr.net/npm/chart.js'",
                $contents,
                $viewFile->getRelativePathname()
            );
        }
    }
}
