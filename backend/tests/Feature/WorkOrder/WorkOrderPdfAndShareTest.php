<?php

declare(strict_types=1);

namespace Tests\Feature\WorkOrder;

use App\Models\Customer;
use App\Models\Vehicle;
use App\Services\WorkOrderPdfService;
use App\Services\WorkOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Tests\TestCase;

final class WorkOrderPdfAndShareTest extends TestCase
{
    use RefreshDatabase;

    public function test_pdf_download_returns_pdf_bytes(): void
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
            'name' => 'PDF Cust',
            'is_active' => true,
        ]);
        $vehicle = Vehicle::create([
            'uuid' => Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'created_by_user_id' => $user->id,
            'plate_number' => 'PDF-1',
            'make' => 'X',
            'model' => 'Y',
            'year' => 2024,
        ]);

        $order = app(WorkOrderService::class)->create(
            [
                'customer_id' => $customer->id,
                'vehicle_id' => $vehicle->id,
                'items' => [['item_type' => 'service', 'name' => 'Line', 'quantity' => 1, 'unit_price' => 10, 'tax_rate' => 15]],
            ],
            $company->id,
            $branch->id,
            $user->id,
        );

        $response = $this->actingAs($user, 'sanctum')
            ->get('/api/v1/work-orders/'.$order->id.'/pdf');

        $response->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent() ?: '');
        $this->assertStringContainsString('pdf', strtolower((string) $response->headers->get('content-type')));
    }

    public function test_share_links_json_includes_public_url(): void
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
            'name' => 'L Cust',
            'is_active' => true,
        ]);
        $vehicle = Vehicle::create([
            'uuid' => Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'created_by_user_id' => $user->id,
            'plate_number' => 'LK-1',
            'make' => 'X',
            'model' => 'Y',
            'year' => 2024,
        ]);

        $order = app(WorkOrderService::class)->create(
            [
                'customer_id' => $customer->id,
                'vehicle_id' => $vehicle->id,
                'driver_phone' => '0512345678',
                'items' => [['item_type' => 'service', 'name' => 'Line', 'quantity' => 1, 'unit_price' => 10, 'tax_rate' => 15]],
            ],
            $company->id,
            $branch->id,
            $user->id,
        );

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/work-orders/'.$order->id.'/share-links');

        $response->assertOk();
        $payload = $response->json('data');
        $this->assertIsArray($payload);
        $this->assertStringContainsString('/public/work-orders/'.$order->uuid, (string) ($payload['public_verify_url'] ?? ''));
        $this->assertStringStartsWith('https://wa.me/', (string) ($payload['whatsapp_driver_href'] ?? ''));
    }

    public function test_public_verify_url_prefers_app_urls_public_base_over_internal_app_url(): void
    {
        $prevAppUrl = config('app.url');
        $prevPublicBase = config('app_urls.public_base');
        try {
            Config::set('app.url', 'http://nginx');
            Config::set('app_urls.public_base', 'https://portal.example.test');

            $company = $this->createCompany();
            $branch = $this->createBranch($company);
            $user = $this->createUser($company, $branch);
            $this->createActiveSubscription($company);

            $customer = Customer::create([
                'uuid' => Str::uuid(),
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'type' => 'individual',
                'name' => 'URL Cust',
                'is_active' => true,
            ]);
            $vehicle = Vehicle::create([
                'uuid' => Str::uuid(),
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'customer_id' => $customer->id,
                'created_by_user_id' => $user->id,
                'plate_number' => 'U-1',
                'make' => 'X',
                'model' => 'Y',
                'year' => 2024,
            ]);

            $order = app(WorkOrderService::class)->create(
                [
                    'customer_id' => $customer->id,
                    'vehicle_id' => $vehicle->id,
                    'items' => [['item_type' => 'service', 'name' => 'Line', 'quantity' => 1, 'unit_price' => 10, 'tax_rate' => 15]],
                ],
                $company->id,
                $branch->id,
                $user->id,
            );

            $this->assertSame(
                'https://portal.example.test/public/work-orders/'.$order->uuid,
                app(WorkOrderPdfService::class)->publicCardUrl($order),
            );

            $this->actingAs($user, 'sanctum')
                ->getJson('/api/v1/work-orders/'.$order->id.'/share-links')
                ->assertOk()
                ->assertJsonPath('data.public_verify_url', 'https://portal.example.test/public/work-orders/'.$order->uuid);
        } finally {
            Config::set('app.url', $prevAppUrl);
            Config::set('app_urls.public_base', $prevPublicBase);
        }
    }

    public function test_share_email_attaches_pdf(): void
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
            'name' => 'M Cust',
            'is_active' => true,
        ]);
        $vehicle = Vehicle::create([
            'uuid' => Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'created_by_user_id' => $user->id,
            'plate_number' => 'EM-1',
            'make' => 'X',
            'model' => 'Y',
            'year' => 2024,
        ]);

        $order = app(WorkOrderService::class)->create(
            [
                'customer_id' => $customer->id,
                'vehicle_id' => $vehicle->id,
                'items' => [['item_type' => 'service', 'name' => 'Line', 'quantity' => 1, 'unit_price' => 10, 'tax_rate' => 15]],
            ],
            $company->id,
            $branch->id,
            $user->id,
        );

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/work-orders/'.$order->id.'/share-email', [
                'email' => 'recipient@example.test',
            ])
            ->assertOk();
    }

}
