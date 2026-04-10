<?php

declare(strict_types=1);

namespace Tests\Feature\WorkOrder;

use App\Models\Customer;
use App\Models\Vehicle;
use App\Services\SensitivePreviewTokenService;
use Illuminate\Support\Str;
use Tests\TestCase;

final class WorkOrderBatchApiTest extends TestCase
{
    public function test_batch_validation_requires_sensitive_preview_token(): void
    {
        $company = $this->createCompany();
        $branch = $this->createBranch($company);
        $user = $this->createUser($company, $branch);
        $this->createActiveSubscription($company);

        $customer = Customer::create([
            'uuid' => Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'type' => 'individual',
            'name' => 'Batch Cust',
            'is_active' => true,
        ]);
        $vehicle = Vehicle::create([
            'uuid' => Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'created_by_user_id' => $user->id,
            'plate_number' => 'BAT-VAL',
            'make' => 'X',
            'model' => 'Y',
            'year' => 2024,
        ]);

        $lines = [
            [
                'customer_id' => $customer->id,
                'vehicle_id' => $vehicle->id,
                'items' => [
                    [
                        'item_type' => 'labor',
                        'name' => 'Svc',
                        'quantity' => 1,
                        'unit_price' => 10,
                        'tax_rate' => 15,
                    ],
                ],
            ],
        ];

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/work-orders/batches', ['lines' => $lines])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['sensitive_preview_token']);
    }

    public function test_batch_rejects_when_fingerprint_mismatches_preview(): void
    {
        $company = $this->createCompany();
        $branch = $this->createBranch($company);
        $user = $this->createUser($company, $branch);
        $this->createActiveSubscription($company);

        $c1 = Customer::create([
            'uuid' => Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'type' => 'individual',
            'name' => 'C1',
            'is_active' => true,
        ]);
        $v1 = Vehicle::create([
            'uuid' => Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'customer_id' => $c1->id,
            'created_by_user_id' => $user->id,
            'plate_number' => 'BAT-1',
            'make' => 'X',
            'model' => 'Y',
            'year' => 2024,
        ]);
        $c2 = Customer::create([
            'uuid' => Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'type' => 'individual',
            'name' => 'C2',
            'is_active' => true,
        ]);
        $v2 = Vehicle::create([
            'uuid' => Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'customer_id' => $c2->id,
            'created_by_user_id' => $user->id,
            'plate_number' => 'BAT-2',
            'make' => 'X',
            'model' => 'Y',
            'year' => 2024,
        ]);

        $previewLines = [
            [
                'customer_id' => $c1->id,
                'vehicle_id' => $v1->id,
                'items' => [
                    ['item_type' => 'labor', 'name' => 'A', 'quantity' => 1, 'unit_price' => 10, 'tax_rate' => 15],
                ],
            ],
        ];
        $token = $this->obtainSensitivePreviewToken(
            $user,
            SensitivePreviewTokenService::OP_BATCH_CREATE,
            [],
            $previewLines,
        );

        $wrongLines = [
            [
                'customer_id' => $c2->id,
                'vehicle_id' => $v2->id,
                'items' => [
                    ['item_type' => 'labor', 'name' => 'B', 'quantity' => 1, 'unit_price' => 10, 'tax_rate' => 15],
                ],
            ],
        ];

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/work-orders/batches', [
                'sensitive_preview_token' => $token,
                'lines' => $wrongLines,
            ])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'محتوى الدفعة لا يطابق المراجعة — أعد المعاينة.']);
    }

    public function test_batch_succeeds_and_preview_token_is_single_use(): void
    {
        $company = $this->createCompany();
        $branch = $this->createBranch($company);
        $user = $this->createUser($company, $branch);
        $this->createActiveSubscription($company);

        $customer = Customer::create([
            'uuid' => Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'type' => 'individual',
            'name' => 'Batch OK',
            'is_active' => true,
        ]);
        $vehicle = Vehicle::create([
            'uuid' => Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'created_by_user_id' => $user->id,
            'plate_number' => 'BAT-OK',
            'make' => 'X',
            'model' => 'Y',
            'year' => 2024,
        ]);

        $lines = [
            [
                'customer_id' => $customer->id,
                'vehicle_id' => $vehicle->id,
                'items' => [
                    [
                        'item_type' => 'labor',
                        'name' => 'Line job',
                        'quantity' => 1,
                        'unit_price' => 25,
                        'tax_rate' => 15,
                    ],
                ],
            ],
        ];

        $token = $this->obtainSensitivePreviewToken(
            $user,
            SensitivePreviewTokenService::OP_BATCH_CREATE,
            [],
            $lines,
        );

        $first = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/work-orders/batches', [
                'sensitive_preview_token' => $token,
                'lines' => $lines,
            ]);
        $first->assertStatus(201)->assertJsonPath('data.status', 'completed');
        $this->assertNotEmpty($first->json('data.items'));

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/work-orders/batches', [
                'sensitive_preview_token' => $token,
                'lines' => $lines,
            ])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'رمز المراجعة منتهٍ أو غير صالح. أعد فتح نافذة المراجعة.']);
    }

    public function test_batch_preview_returns_token_and_fingerprint_coverage(): void
    {
        $company = $this->createCompany();
        $branch = $this->createBranch($company);
        $user = $this->createUser($company, $branch);
        $this->createActiveSubscription($company);

        $customer = Customer::create([
            'uuid' => Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'type' => 'individual',
            'name' => 'Prev',
            'is_active' => true,
        ]);
        $vehicle = Vehicle::create([
            'uuid' => Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'created_by_user_id' => $user->id,
            'plate_number' => 'BAT-PV',
            'make' => 'X',
            'model' => 'Y',
            'year' => 2024,
        ]);

        $lines = [
            [
                'customer_id' => $customer->id,
                'vehicle_id' => $vehicle->id,
                'items' => [
                    ['item_type' => 'labor', 'name' => 'P', 'quantity' => 1, 'unit_price' => 5, 'tax_rate' => 15],
                ],
            ],
        ];

        $res = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/sensitive-operations/preview', [
                'operation' => SensitivePreviewTokenService::OP_BATCH_CREATE,
                'lines' => $lines,
            ]);

        $res->assertOk();
        $tok = $res->json('data.sensitive_preview_token');
        $this->assertNotEmpty($tok);
        $this->assertSame(SensitivePreviewTokenService::OP_BATCH_CREATE, $res->json('data.operation'));
        $this->assertSame($branch->name, $res->json('data.branch_name'));
    }
}
