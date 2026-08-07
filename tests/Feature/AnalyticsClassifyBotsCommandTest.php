<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class AnalyticsClassifyBotsCommandTest extends TestCase
{
    public function test_analytics_bot_classification_command_is_not_registered(): void
    {
        $this->assertArrayNotHasKey('analytics:classify-bots', Artisan::all());
    }
}
