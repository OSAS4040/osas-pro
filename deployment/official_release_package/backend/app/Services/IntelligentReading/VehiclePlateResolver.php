<?php

namespace App\Services\IntelligentReading;

use App\Models\Vehicle;
use App\Models\VehicleDocument;

final class VehiclePlateResolver
{
    /**
     * @return array{registered: bool, plate?: string, reason?: string, vehicle?: Vehicle, documents?: \Illuminate\Support\Collection, recent_work_orders?: \Illuminate\Support\Collection}
     */
    public static function resolve(int $companyId, string $plateInput): array
    {
        $norm = KsaPlateNormalizer::normalize($plateInput);
        if (! $norm) {
            return ['registered' => false, 'reason' => 'invalid_plate'];
        }

        $compact = strtoupper($norm['compact']);
        $vehicle = Vehicle::query()
            ->with(['customer', 'branch'])
            ->where('company_id', $companyId)
            ->whereRaw("upper(replace(plate_number, ' ', '')) = ?", [$compact])
            ->first();

        if (! $vehicle) {
            return [
                'registered' => false,
                'plate' => $norm['display'],
                'reason' => 'not_found',
            ];
        }

        $docs = VehicleDocument::query()
            ->where('vehicle_id', $vehicle->id)
            ->where('company_id', $companyId)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get(['id', 'document_type', 'title', 'expiry_date', 'file_name', 'created_at']);

        $recentWo = $vehicle->workOrders()
            ->latest()
            ->limit(5)
            ->get(['id', 'order_number', 'status', 'created_at']);

        return [
            'registered' => true,
            'plate' => $vehicle->plate_number,
            'vehicle' => $vehicle,
            'documents' => $docs,
            'recent_work_orders' => $recentWo,
        ];
    }
}
