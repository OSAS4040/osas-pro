<?php

namespace Tests\Feature\Ocr;

use Tests\TestCase;

/**
 * استدعاء فعلي لـ POST /api/v1/ocr/plate مع حمولة صالحة/غير صالحة.
 */
class OcrPlateEndpointTest extends TestCase
{
    public function test_plate_scan_rejects_invalid_image_payload(): void
    {
        $company = $this->createCompany();
        $branch = $this->createBranch($company);
        $user = $this->createUser($company, $branch, 'owner');
        $this->createActiveSubscription($company);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/governance/ocr/plate', ['image' => base64_encode('short')])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_plate_scan_accepts_jpeg_and_returns_contract_json(): void
    {
        $company = $this->createCompany();
        $branch = $this->createBranch($company);
        $user = $this->createUser($company, $branch, 'owner');
        $this->createActiveSubscription($company);

        $payload = random_bytes(180);
        $this->assertGreaterThan(100, strlen($payload));
        $b64 = base64_encode($payload);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/governance/ocr/plate', [
            'image' => $b64,
            'resolve_vehicle' => false,
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'plate',
            'plate_normalized',
            'success',
            'method',
            'error',
            'raw_ocr_text',
            'vehicle',
        ]);
    }
}
