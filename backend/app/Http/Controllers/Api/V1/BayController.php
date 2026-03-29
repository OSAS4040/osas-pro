<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Bay;
use App\Models\Booking;
use App\Services\BookingService;
use App\Services\AuditLogger;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BayController extends Controller
{
    // ── Bays ──────────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $bays = Bay::where('company_id', $user->company_id)
            ->when($request->query('branch_id'), fn ($q, $v) => $q->where('branch_id', $v))
            ->when($request->query('status'),    fn ($q, $v) => $q->where('status', $v))
            ->get();
        return response()->json(['data' => $bays]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'code'         => 'required|string|max:20',
            'name'         => 'required|string|max:80',
            'type'         => 'nullable|string|in:lift,bay,wash,alignment',
            'capacity'     => 'nullable|integer|min:1',
            'capabilities' => 'nullable|array',
            'branch_id'    => 'nullable|integer',
        ]);

        try {
            $bay = Bay::create(array_merge($data, [
                'company_id' => $user->company_id,
                'branch_id'  => $data['branch_id'] ?? $user->branch_id,
                'status'     => 'available',
            ]));
        } catch (UniqueConstraintViolationException $e) {
            return response()->json(['message' => 'الرافعة موجودة مسبقاً (unique).'], 422);
        } catch (\Illuminate\Database\QueryException $e) {
            if (str_contains($e->getMessage(), 'duplicate') || str_contains($e->getMessage(), 'unique')) {
                return response()->json(['message' => 'الرافعة موجودة مسبقاً (unique).'], 422);
            }
            throw $e;
        }

        app(AuditLogger::class)->log('bay.created', Bay::class, $bay->id, [], $bay->toArray());

        return response()->json(['data' => $bay, 'message' => 'تم إنشاء الرافعة.'], 201);
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $user   = $request->user();
        $bay    = Bay::where('company_id', $user->company_id)->findOrFail($id);
        $before = ['status' => $bay->status];

        $data = $request->validate([
            'status' => 'required|string|in:available,reserved,in_use,maintenance,out_of_service',
            'notes'  => 'nullable|string',
        ]);

        $bay->update($data);
        app(AuditLogger::class)->change($bay, 'bay.status_changed', $before, ['status' => $bay->status]);

        return response()->json(['data' => $bay->fresh(), 'message' => 'تم تحديث حالة الرافعة.']);
    }

    // ── Bookings ──────────────────────────────────────────────────────

    public function listBookings(Request $request): JsonResponse
    {
        $user     = $request->user();
        $bookings = Booking::where('company_id', $user->company_id)
            ->when($request->query('date'),      fn ($q, $v) => $q->whereDate('starts_at', $v))
            ->when($request->query('status'),    fn ($q, $v) => $q->where('status', $v))
            ->when($request->query('bay_id'),    fn ($q, $v) => $q->where('bay_id', $v))
            ->with(['bay:id,code,name', 'customer:id,name', 'vehicle:id,plate_number'])
            ->orderBy('starts_at')
            ->paginate(30);

        return response()->json($bookings);
    }

    public function storeBooking(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'bay_id'           => 'required|integer',
            'starts_at'        => 'required|date',
            'duration_minutes' => 'required|integer|min:15|max:480',
            'customer_id'      => 'nullable|integer',
            'vehicle_id'       => 'nullable|integer',
            'service_type'     => 'nullable|string|max:80',
            'notes'            => 'nullable|string',
            'source'           => 'nullable|string|in:manual,fleet_portal,online',
        ]);

        Bay::where('company_id', $user->company_id)->findOrFail($data['bay_id']);

        $start = \Carbon\Carbon::parse($data['starts_at']);
        $end   = $start->copy()->addMinutes($data['duration_minutes']);

        $booking = app(BookingService::class)->book(array_merge($data, [
            'company_id' => $user->company_id,
            'branch_id'  => $user->branch_id,
            'ends_at'    => $end,
            'status'     => 'confirmed',
            'created_by' => $user->id,
        ]));

        return response()->json(['data' => $booking->load('bay:id,code,name'), 'message' => 'تم الحجز بنجاح.'], 201);
    }

    public function updateBooking(Request $request, int $id): JsonResponse
    {
        $user    = $request->user();
        $booking = Booking::where('company_id', $user->company_id)->findOrFail($id);
        $action  = $request->input('action');
        $svc     = app(BookingService::class);

        $result = match ($action) {
            'confirm'  => $svc->confirm($id),
            'start'    => $svc->start($id),
            'complete' => $svc->complete($id),
            'cancel'   => $svc->cancel($id, $user->id, $request->input('reason', '')),
            default    => $booking,
        };

        return response()->json(['data' => $result, 'message' => 'تم التحديث.']);
    }

    public function checkAvailability(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'branch_id'        => 'required|integer',
            'starts_at'        => 'required|date',
            'duration_minutes' => 'required|integer|min:15',
            'capability'       => 'nullable|string',
        ]);

        $start = \Carbon\Carbon::parse($data['starts_at']);
        $end   = $start->copy()->addMinutes($data['duration_minutes']);

        $bay = app(BookingService::class)->findAvailableBay(
            $user->company_id, $data['branch_id'], $start, $end, $data['capability'] ?? null
        );

        return response()->json([
            'available' => (bool) $bay,
            'bay'       => $bay,
        ]);
    }

    public function heatmap(Request $request): JsonResponse
    {
        $user   = $request->user();
        $date   = $request->query('date', today()->toDateString());
        $branch = $request->query('branch_id', $user->branch_id);

        $data = app(BookingService::class)->heatmap($user->company_id, $branch, $date);

        return response()->json(['data' => $data, 'date' => $date]);
    }
}
