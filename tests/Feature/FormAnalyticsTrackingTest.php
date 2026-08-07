<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FormAnalyticsTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_form_analytics_storage_is_removed(): void
    {
        $this->assertFalse(Schema::hasTable('form_events'));
        $this->assertFalse(Schema::hasTable('analytics_events'));
        $this->assertFalse(Schema::hasTable('analytics_page_views'));
        $this->assertFalse(Schema::hasTable('analytics_visits'));
    }
}
