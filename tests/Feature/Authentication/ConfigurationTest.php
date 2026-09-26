<?php

namespace Tests\Feature\Authentication;

use Tests\TestCase;

class ConfigurationTest extends TestCase
{
    public function test_sanctum_and_hashing_configuration_is_explicitly_available(): void
    {
        $this->assertSame(['web'], config('sanctum.guard'));
        $this->assertNull(config('sanctum.expiration'));
        $this->assertSame('bcrypt', config('hashing.driver'));
        $this->assertGreaterThanOrEqual(4, (int) config('hashing.bcrypt.rounds'));
    }

    public function test_cors_configuration_is_restricted_to_api_paths_and_known_origins(): void
    {
        $this->assertSame(['api/*'], config('cors.paths'));
        $this->assertNotContains('*', config('cors.allowed_origins'));
        $this->assertContains('http://localhost:3000', config('cors.allowed_origins'));
        $this->assertContains('Authorization', config('cors.allowed_headers'));
        $this->assertFalse(config('cors.supports_credentials'));
    }

    public function test_cors_preflight_allows_a_configured_frontend_origin(): void
    {
        $this->withHeaders([
            'Origin' => 'http://localhost:3000',
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'Content-Type, Authorization',
        ])
            ->optionsJson('/api/login')
            ->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:3000');
    }

    public function test_cors_preflight_does_not_allow_an_unconfigured_origin(): void
    {
        $this->withHeaders([
            'Origin' => 'https://untrusted.example',
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'Content-Type, Authorization',
        ])
            ->optionsJson('/api/login')
            ->assertNoContent()
            ->assertHeaderMissing('Access-Control-Allow-Origin');
    }
}
