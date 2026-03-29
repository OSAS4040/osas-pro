<?php

namespace App\Jobs;

use App\Enums\ReservationStatus;
use App\Models\Inventory;
use App\Models\InventoryReservation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ExpireInventoryReservationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        InventoryReservation::where('status', ReservationStatus::Pending)
            ->where('expires_at', '<', now())
            ->each(function (InventoryReservation $reservation) {
                DB::transaction(function () use ($reservation) {
                    Inventory::where('id', $reservation->inventory_id)
                        ->lockForUpdate()
                        ->decrement('reserved_quantity', $reservation->quantity);

                    $reservation->update(['status' => ReservationStatus::Expired]);
                });
            });
    }
}
