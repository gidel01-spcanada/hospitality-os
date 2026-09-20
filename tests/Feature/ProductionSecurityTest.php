<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_headers_are_present(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_404_error_page_renders_a_custom_response(): void
    {
        $this->get('/this-page-does-not-exist')
            ->assertNotFound()
            ->assertSeeText('Page introuvable');
    }

    public function test_404_error_page_uses_the_selected_locale(): void
    {
        $this->withSession(['locale' => 'en'])
            ->get('/missing-page-for-locale-test')
            ->assertNotFound()
            ->assertSee('lang="en"', false)
            ->assertSee('Error 404')
            ->assertSee('Page not found');
    }
}
