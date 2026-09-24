<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_responses_are_locked_down(): void
    {
        $res = $this->getJson('/api/v1/time');
        $res->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'")
            ->assertHeaderMissing('X-Powered-By');
        $this->assertStringContainsString('no-store', $res->headers->get('Cache-Control'));
    }

    public function test_panels_cannot_be_framed_elsewhere(): void
    {
        $this->get('/admin/login')->assertOk()->assertHeader('X-Frame-Options', 'SAMEORIGIN')->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }
}
