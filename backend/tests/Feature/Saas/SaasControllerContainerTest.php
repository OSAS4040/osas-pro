<?php

declare(strict_types=1);

namespace Tests\Feature\Saas;

use App\Http\Controllers\Api\V1\SaasController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * يضمن أن بناء المتحكم لا يستدعي parent::__construct() على Illuminate\Routing\Controller
 * (ما يسبب Cannot call constructor في PHP 8+).
 */
final class SaasControllerContainerTest extends TestCase
{
    use RefreshDatabase;

    public function test_saas_controller_resolves_from_container(): void
    {
        $c = $this->app->make(SaasController::class);
        $this->assertInstanceOf(SaasController::class, $c);
    }

    public function test_public_plans_endpoint_succeeds(): void
    {
        $res = $this->getJson('/api/v1/plans');
        $res->assertSuccessful();
        $res->assertJsonStructure(['data', 'trace_id']);
    }
}
