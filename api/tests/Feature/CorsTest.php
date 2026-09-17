<?php

namespace Tests\Feature;

use Tests\TestCase;

class CorsTest extends TestCase
{
    public function test_preflight_from_allowed_origin_is_accepted(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'http://localhost:5173',
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'authorization, content-type',
        ])->options('/api/login');

        $response->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173');
    }

    public function test_preflight_from_unknown_origin_is_rejected(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'http://evil.example',
            'Access-Control-Request-Method' => 'POST',
        ])->options('/api/login');

        $response->assertHeaderMissing('Access-Control-Allow-Origin');
    }
}
