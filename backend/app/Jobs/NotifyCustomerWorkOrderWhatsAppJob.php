<?php

namespace App\Jobs;

use App\Models\WorkOrder;
use App\Services\Messaging\WhatsAppOutboundService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class NotifyCustomerWorkOrderWhatsAppJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 30, 60];

    public int $timeout = 45;

    public bool $failOnTimeout = true;

    public function __construct(
        public readonly int $workOrderId,
        public readonly int $companyId,
        public readonly string $kind,
    ) {
        // Allow queue push while an outer transaction is still open (e.g. PHPUnit RefreshDatabase).
        // Work order status is already committed inside WorkOrderService's inner transaction.
        $this->beforeCommit();
    }

    public function handle(WhatsAppOutboundService $messaging): void
    {
        if (! config('whatsapp_work_order_notifications.enabled')) {
            Log::info('whatsapp.work_order.skipped_feature_disabled', [
                'work_order_id' => $this->workOrderId,
                'company_id'    => $this->companyId,
                'kind'          => $this->kind,
            ]);

            return;
        }

        if (! app()->bound('trace_id')) {
            app()->instance('trace_id', (string) Str::uuid());
        }

        $workOrder = WorkOrder::withoutGlobalScope('tenant')->find($this->workOrderId);

        if (! $workOrder || (int) $workOrder->company_id !== $this->companyId) {
            Log::warning('whatsapp.work_order.job_invalid_order', [
                'work_order_id' => $this->workOrderId,
                'company_id'    => $this->companyId,
                'kind'          => $this->kind,
            ]);

            return;
        }

        $messaging->sendOperationalWorkOrderMessage($workOrder, $this->kind);
    }

    public function failed(?\Throwable $e): void
    {
        Log::error('whatsapp.work_order.job_failed', [
            'work_order_id' => $this->workOrderId,
            'company_id'    => $this->companyId,
            'kind'          => $this->kind,
            'error'         => $e?->getMessage(),
        ]);
    }
}
