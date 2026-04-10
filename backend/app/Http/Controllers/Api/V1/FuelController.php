<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\FuelLog;
use App\Models\Vehicle;
use App\Models\VehicleSetting;
use App\Models\VehicleDocument;
use App\Support\Media\TenantUploadDisk;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FuelController extends Controller
{
    private function cid(): int { return app('tenant_company_id'); }

    public function index(Request $request): JsonResponse
    {
        $q = FuelLog::where('company_id', $this->cid())
            ->with(['vehicle:id,plate_number,make,model', 'driver:id,name'])
            ->orderByDesc('log_date');

        if ($request->vehicle_id) $q->where('vehicle_id', $request->vehicle_id);
        if ($request->from)       $q->where('log_date', '>=', $request->from);
        if ($request->to)         $q->where('log_date', '<=', $request->to);
        if ($request->fuel_type)  $q->where('fuel_type', $request->fuel_type);

        return response()->json($q->paginate((int)($request->per_page ?? 50)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'vehicle_id'       => 'required|integer',
            'log_date'         => 'required|date',
            'liters'           => 'required|numeric|min:0.1',
            'price_per_liter'  => 'required|numeric|min:0',
            'odometer_after'   => 'nullable|numeric|min:0',
            'odometer_before'  => 'nullable|numeric|min:0',
            'fuel_type'        => 'nullable|in:91,95,98,diesel,electric',
            'station_name'     => 'nullable|string|max:120',
            'payment_method'   => 'nullable|string|max:30',
            'driver_user_id'   => 'nullable|integer',
            'notes'            => 'nullable|string',
            'receipt_number'   => 'nullable|string|max:80',
        ]);

        $data['company_id'] = $this->cid();
        $data['branch_id']  = app('tenant_branch_id');
        $data['created_by'] = auth()->id();

        // Calculate fuel efficiency
        if (!empty($data['odometer_before']) && !empty($data['odometer_after']) && $data['liters'] > 0) {
            $km = $data['odometer_after'] - $data['odometer_before'];
            $data['fuel_efficiency'] = round($km / $data['liters'], 3);
        }

        // Update vehicle odometer
        if (!empty($data['odometer_after'])) {
            Vehicle::where('id', $data['vehicle_id'])->where('company_id', $this->cid())
                ->update(['current_odometer' => $data['odometer_after']]);
        }

        $log = FuelLog::create($data);

        return response()->json($log->load(['vehicle:id,plate_number,make,model', 'driver:id,name']), 201);
    }

    public function destroy(int $id): JsonResponse
    {
        FuelLog::where('company_id', $this->cid())->findOrFail($id)->delete();
        return response()->json(null, 204);
    }

    public function stats(Request $request): JsonResponse
    {
        $cid  = $this->cid();
        $from = $request->from ?? now()->startOfMonth()->toDateString();
        $to   = $request->to   ?? now()->toDateString();

        $base = FuelLog::where('company_id', $cid)
            ->whereBetween('log_date', [$from, $to]);

        $totals = (clone $base)->selectRaw('
            COUNT(*) as total_logs,
            SUM(liters) as total_liters,
            SUM(total_cost) as total_cost,
            AVG(total_cost) as avg_cost_per_fill,
            AVG(fuel_efficiency) as avg_efficiency
        ')->first();

        $byVehicle = (clone $base)->selectRaw('
            vehicle_id, SUM(liters) as liters, SUM(total_cost) as cost,
            AVG(fuel_efficiency) as efficiency, COUNT(*) as fills
        ')->with('vehicle:id,plate_number,make,model')
            ->groupBy('vehicle_id')->orderByDesc('cost')->limit(10)->get();

        $byFuelType = (clone $base)->selectRaw('fuel_type, SUM(liters) as liters, SUM(total_cost) as cost')
            ->groupBy('fuel_type')->get();

        $trend = (clone $base)->selectRaw("TO_CHAR(log_date,'YYYY-MM') as month, SUM(liters) as liters, SUM(total_cost) as cost")
            ->groupByRaw("TO_CHAR(log_date,'YYYY-MM')")->orderBy('month')->get();

        return response()->json(compact('totals', 'byVehicle', 'byFuelType', 'trend'));
    }

    // ─── Vehicle Settings ──────────────────────────────────────────────────

    public function getSettings(int $vehicleId): JsonResponse
    {
        $vehicle = Vehicle::where('company_id', $this->cid())->findOrFail($vehicleId);
        $settings = VehicleSetting::firstOrCreate(['vehicle_id' => $vehicleId]);
        return response()->json($settings);
    }

    public function saveSettings(Request $request, int $vehicleId): JsonResponse
    {
        Vehicle::where('company_id', $this->cid())->findOrFail($vehicleId);
        $data = $request->validate([
            'oil_type'                  => 'nullable|string|max:80',
            'oil_capacity_liters'       => 'nullable|string|max:20',
            'oil_change_interval_km'    => 'nullable|integer',
            'last_oil_change_km'        => 'nullable|numeric',
            'last_oil_change_date'      => 'nullable|date',
            'tire_size'                 => 'nullable|string|max:40',
            'tire_brand'                => 'nullable|string|max:80',
            'tire_change_date'          => 'nullable|date',
            'battery_brand'             => 'nullable|string|max:80',
            'battery_capacity_ah'       => 'nullable|string|max:20',
            'battery_change_date'       => 'nullable|date',
            'ac_gas_type'               => 'nullable|string|max:40',
            'last_ac_service_date'      => 'nullable|date',
            'insurance_expiry'          => 'nullable|date',
            'registration_expiry'       => 'nullable|date',
            'next_inspection_date'      => 'nullable|date',
            'custom_settings'           => 'nullable|array',
        ]);

        $settings = VehicleSetting::updateOrCreate(['vehicle_id' => $vehicleId], $data);
        return response()->json($settings);
    }

    // ─── Vehicle Documents ─────────────────────────────────────────────────

    public function getDocuments(int $vehicleId): JsonResponse
    {
        Vehicle::where('company_id', $this->cid())->findOrFail($vehicleId);
        $docs = VehicleDocument::where('vehicle_id', $vehicleId)
            ->where('company_id', $this->cid())
            ->orderByDesc('created_at')->get();
        return response()->json($docs);
    }

    public function uploadDocument(Request $request, int $vehicleId): JsonResponse
    {
        Vehicle::where('company_id', $this->cid())->findOrFail($vehicleId);
        $request->validate([
            'document_type' => 'required|in:insurance,registration,technical,license,other',
            'title'         => 'required|string|max:160',
            'file'          => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png,webp',
            'expiry_date'   => 'nullable|date',
            'alert_days_before' => 'nullable|integer|min:1|max:365',
            'notes'         => 'nullable|string',
        ]);

        $file = $request->file('file');
        $path = $file->store("companies/{$this->cid()}/vehicle-docs/{$vehicleId}", TenantUploadDisk::name());

        $doc = VehicleDocument::create([
            'company_id'       => $this->cid(),
            'vehicle_id'       => $vehicleId,
            'uploaded_by'      => auth()->id(),
            'document_type'    => $request->document_type,
            'title'            => $request->title,
            'file_path'        => $path,
            'file_name'        => $file->getClientOriginalName(),
            'file_size'        => $file->getSize(),
            'expiry_date'      => $request->expiry_date,
            'alert_days_before'=> $request->alert_days_before ?? 30,
            'notes'            => $request->notes,
        ]);

        return response()->json($doc, 201);
    }

    public function deleteDocument(int $vehicleId, int $docId): JsonResponse
    {
        $doc = VehicleDocument::where('company_id', $this->cid())
            ->where('vehicle_id', $vehicleId)->findOrFail($docId);
        Storage::disk(TenantUploadDisk::name())->delete($doc->file_path);
        $doc->delete();
        return response()->json(null, 204);
    }
}
