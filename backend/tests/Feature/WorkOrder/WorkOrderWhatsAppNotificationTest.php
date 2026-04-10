<?php

namespace Tests\Feature\WorkOrder;

use App\Enums\WorkOrderStatus;
use App\Jobs\NotifyCustomerWorkOrderWhatsAppJob;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Customer;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use App\Services\Messaging\WhatsAppOutboundService;
use App\Services\WorkOrderService;
use Illuminate\Queue\SyncQueue;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class WorkOrderWhatsAppNotificationTest extends TestCase
{
    private Company $company;

    private Branch $branch;

    private User $user;

    private Customer $customer;

    private Vehicle $vehicle;

    private WorkOrderService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // يفرض phpunit.xml ‎QUEUE_CONNECTION=sync‎؛ إن وُجدت كاش إعدادات أو بيئة حاوية تُعيد ‎redis‎ نُثبت التزامن هنا.
        Config::set('queue.default', 'sync');

        $this->company = $this->createCompany();
        $this->branch = $this->createBranch($this->company);
        $this->user = $this->createUser($this->company, $this->branch);
        $this->createActiveSubscription($this->company);

        $this->customer = Customer::create([
            'uuid'       => Str::uuid(),
            'company_id' => $this->company->id,
            'name'       => 'WA Test Customer',
            'type'       => 'individual',
            'phone'      => '0501234567',
            'is_active'  => true,
        ]);

        $this->vehicle = Vehicle::create([
            'uuid'               => Str::uuid(),
            'company_id'         => $this->company->id,
            'branch_id'          => $this->branch->id,
            'customer_id'        => $this->customer->id,
            'created_by_user_id' => $this->user->id,
            'plate_number'       => 'WA-001',
            'make'               => 'Toyota',
            'model'              => 'Camry',
            'year'               => 2022,
        ]);

        $this->service = app(WorkOrderService::class);
    }

    protected function tearDown(): void
    {
        if (Bus::isFake()) {
            Bus::swap(Bus::getFacadeRoot()->dispatcher);
        }
        parent::tearDown();
    }

    private function mergeWhatsAppSettings(array $whatsapp): void
    {
        $settings = $this->company->settings ?? [];
        $settings['whatsapp'] = array_merge($settings['whatsapp'] ?? [], $whatsapp);
        $this->company->update(['settings' => $settings]);
        $this->company->refresh();
    }

    private function createOrder(): WorkOrder
    {
        return $this->service->create(
            [
                'customer_id' => $this->customer->id,
                'vehicle_id' => $this->vehicle->id,
                'items' => [$this->minimalWorkOrderLineItem()],
            ],
            $this->company->id,
            $this->branch->id,
            $this->user->id,
        );
    }

    public function test_dispatches_whatsapp_jobs_on_completed_and_delivered(): void
    {
        Bus::fake([NotifyCustomerWorkOrderWhatsAppJob::class]);

        $order = $this->createOrder();
        $order = $this->service->transition($order, WorkOrderStatus::Approved);
        $order = $this->service->transition($order, WorkOrderStatus::InProgress);
        $order = $this->service->transition($order, WorkOrderStatus::Completed);
        $order = $this->service->transition($order, WorkOrderStatus::Delivered);

        Bus::assertDispatched(NotifyCustomerWorkOrderWhatsAppJob::class, function (NotifyCustomerWorkOrderWhatsAppJob $job) use ($order) {
            return $job->workOrderId === $order->id
                && $job->companyId === $this->company->id
                && $job->kind === 'completed';
        });

        Bus::assertDispatched(NotifyCustomerWorkOrderWhatsAppJob::class, function (NotifyCustomerWorkOrderWhatsAppJob $job) use ($order) {
            return $job->workOrderId === $order->id
                && $job->companyId === $this->company->id
                && $job->kind === 'delivered';
        });
    }

    public function test_minimal_bus_dispatch_triggers_twilio_http(): void
    {
        $this->mergeWhatsAppSettings([
            'provider' => 'twilio',
            'config'   => [
                'twilio_sid'   => 'ACaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
                'twilio_token' => 'test_auth_token',
                'twilio_from'  => '14155238886',
            ],
            'triggers' => [
                'wo_delivered' => true,
            ],
        ]);

        Http::fake(fn () => Http::response(['sid' => 'SMtest'], 201));

        $order = $this->createOrder();
        $this->assertSame('sync', config('queue.default'));
        $this->assertInstanceOf(SyncQueue::class, Queue::connection());
        $job = new NotifyCustomerWorkOrderWhatsAppJob(
            $order->id,
            (int) $this->company->id,
            'delivered',
        );
        $this->assertSame(false, $job->afterCommit);
        Bus::dispatch($job);

        Http::assertSent(function (\Illuminate\Http\Client\Request $request) {
            return str_contains($request->url(), 'api.twilio.com')
                && str_contains($request->url(), 'Messages.json');
        });
    }

    public function test_dispatch_sync_reaches_twilio_when_trigger_enabled(): void
    {
        $this->mergeWhatsAppSettings([
            'provider' => 'twilio',
            'config'   => [
                'twilio_sid'   => 'ACaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
                'twilio_token' => 'test_auth_token',
                'twilio_from'  => '14155238886',
            ],
            'triggers' => [
                'wo_delivered' => true,
            ],
        ]);

        Http::fake(fn () => Http::response(['sid' => 'SMtest'], 201));

        $order = $this->createOrder();
        NotifyCustomerWorkOrderWhatsAppJob::dispatchSync($order->id, (int) $this->company->id, 'delivered');

        Http::assertSent(function (\Illuminate\Http\Client\Request $request) {
            return str_contains($request->url(), 'api.twilio.com')
                && str_contains($request->url(), 'Messages.json');
        });
    }

    public function test_twilio_request_when_delivered_trigger_enabled(): void
    {
        $this->mergeWhatsAppSettings([
            'provider' => 'twilio',
            'config'   => [
                'twilio_sid'   => 'ACaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
                'twilio_token' => 'test_auth_token',
                'twilio_from'  => '14155238886',
            ],
            'triggers' => [
                'wo_delivered' => true,
                'wo_completed' => false,
            ],
        ]);

        Http::fake(fn () => Http::response(['sid' => 'SMtest'], 201));

        $order = $this->createOrder();
        $order = $this->service->transition($order, WorkOrderStatus::Approved);
        $order = $this->service->transition($order, WorkOrderStatus::InProgress);
        $order = $this->service->transition($order, WorkOrderStatus::Completed);
        $this->service->transition($order, WorkOrderStatus::Delivered);

        Http::assertSent(function (\Illuminate\Http\Client\Request $request) {
            return str_contains($request->url(), 'api.twilio.com')
                && str_contains($request->url(), 'Messages.json');
        });
    }

    public function test_no_http_when_delivered_trigger_disabled(): void
    {
        $this->mergeWhatsAppSettings([
            'provider' => 'twilio',
            'config'   => [
                'twilio_sid'   => 'ACaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
                'twilio_token' => 'test_auth_token',
                'twilio_from'  => '14155238886',
            ],
            'triggers' => [
                'wo_delivered' => false,
                'wo_completed' => false,
            ],
        ]);

        Http::fake();

        $order = $this->createOrder();
        $order = $this->service->transition($order, WorkOrderStatus::Approved);
        $order = $this->service->transition($order, WorkOrderStatus::InProgress);
        $order = $this->service->transition($order, WorkOrderStatus::Completed);
        $this->service->transition($order, WorkOrderStatus::Delivered);

        Http::assertNothingSent();
    }

    public function test_job_aborts_when_company_mismatch(): void
    {
        $this->mergeWhatsAppSettings([
            'provider' => 'twilio',
            'config'   => [
                'twilio_sid'   => 'ACaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
                'twilio_token' => 'test_auth_token',
                'twilio_from'  => '14155238886',
            ],
            'triggers' => [
                'wo_delivered' => true,
            ],
        ]);

        Http::fake(fn () => Http::response(['sid' => 'SMtest'], 201));

        $order = $this->createOrder();

        $job = new NotifyCustomerWorkOrderWhatsAppJob($order->id, 999_999_999, 'delivered');
        $job->handle(app(WhatsAppOutboundService::class));

        Http::assertNothingSent();
    }
}
