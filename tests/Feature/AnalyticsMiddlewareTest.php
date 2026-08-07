<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AnalyticsMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_party_analytics_tables_are_not_present(): void
    {
        $this->assertFalse(Schema::hasTable('form_events'));
        $this->assertFalse(Schema::hasTable('analytics_events'));
        $this->assertFalse(Schema::hasTable('analytics_page_views'));
        $this->assertFalse(Schema::hasTable('analytics_visits'));
    }
}
