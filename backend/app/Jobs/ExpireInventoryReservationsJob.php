<?php

namespace App\Jobs;

use App\Enums\ReservationStatus;
use App\Models\Inventory;
use App\Models\InventoryReservation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ExpireInventoryReservationsJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 10;
    public int $timeout = 300;
    public int $uniqueFor = 1200;

    /** @var list<int> */
    public array $backoff = [10, 30, 60];

    public function __construct()
    {
        $this->onQueue('low_priority');
    }

    public function handle(): void
    {
        try {
            // Bounded runtime per dispatch: scheduler ticks frequently; avoid exceeding worker timeout
            // under DB contention (peak load / many row locks).
            $deadline = microtime(true) + 90.0;

            InventoryReservation::query()
                ->where('status', ReservationStatus::Pending)
                ->whereNotNull('expires_at')
                ->where('expires_at', '<', now())
                ->orderBy('id')
                ->chunkById(200, function ($reservations) use ($deadline) {
                    if (microtime(true) >= $deadline) {
                        return false;
                    }
                    foreach ($reservations as $reservation) {
                        try {
                            DB::transaction(function () use ($reservation): void {
                                $locked = InventoryReservation::query()
                                    ->whereKey($reservation->id)
                                    ->lockForUpdate()
                                    ->first();

                                if (! $locked || $locked->status !== ReservationStatus::Pending) {
                                    return;
                                }

                                $inv = Inventory::query()
                                    ->whereKey($locked->inventory_id)
                                    ->lockForUpdate()
                                    ->first();

                                if (! $inv) {
                                    $locked->update(['status' => ReservationStatus::Expired]);
                                    return;
                                }

                                $nextReserved = max(0.0, (float) $inv->reserved_quantity - (float) $locked->quantity);
                                $inv->update(['reserved_quantity' => $nextReserved]);
                                $locked->update(['status' => ReservationStatus::Expired]);
                            });
                        } catch (\Throwable $e) {
                            report($e);
                            // Keep worker healthy: skip broken row and continue.
                        }
                    }

                    return null;
                });
        } catch (\Throwable $e) {
            report($e);
            // Prevent recurring scheduler noise from bringing low_priority worker down.
        }
    }
}
