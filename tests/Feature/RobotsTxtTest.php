<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class RobotsTxtTest extends TestCase
{
    public function test_robots_txt_contains_sitemap_directives(): void
    {
        $robotsPath = public_path('robots.txt');
        $this->assertFileExists($robotsPath);

        $robotsContent = (string) File::get($robotsPath);
        $this->assertStringContainsString('Sitemap: https://prop.test/sitemap.xml', $robotsContent);
        $this->assertStringContainsString('Sitemap: https://prop.test/sitemap-index.xml', $robotsContent);
    }
}
