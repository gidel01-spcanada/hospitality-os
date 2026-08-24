<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthTest extends TestCase
{
    public function test_home_page_renders(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Afrik Appart');
    }

    public function test_health_endpoint_returns_status(): void
    {
        $response = $this->get('/health');

        $response->assertOk();
        $response->assertJsonPath('status', 'ok');
        $response->assertJsonPath('application', 'afrikappart');
    }
}
