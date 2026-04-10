<?php

namespace Tests\Feature\System;

use Tests\TestCase;

class SystemVersionEndpointTest extends TestCase
{
    public function test_system_version_returns_deployment_payload(): void
    {
        $response = $this->getJson('/api/v1/system/version');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'version',
                    'commit',
                    'branch',
                    'build_time',
                    'environment',
                ],
                'trace_id',
            ]);
    }
}
