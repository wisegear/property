<?php

namespace Tests\Feature;

use Tests\TestCase;

class AffordabilityRoutesRemovedTest extends TestCase
{
    public function test_affordability_routes_are_not_available(): void
    {
        $this->get('/affordability')->assertNotFound();
        $this->get('/affordability/show/test-token')->assertNotFound();
        $this->post('/affordability/calculate')->assertNotFound();
    }
}
