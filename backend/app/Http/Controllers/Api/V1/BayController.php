<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Bay;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\Company;
use App\Services\BookingService;
use App\Services\AuditLogger;
use App\Services\Config\ConfigResolverService;
use App\Support\BranchOpeningHours;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BayController extends Controller
{
    public function __construct(private readonly ConfigResolverService $configResolver) {}

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
            return response()->json(['message' => 'منطقة العمل موجودة مسبقاً (مكرر).'], 422);
        } catch (\Illuminate\Database\QueryException $e) {
            if (str_contains($e->getMessage(), 'duplicate') || str_contains($e->getMessage(), 'unique')) {
                return response()->json(['message' => 'منطقة العمل موجودة مسبقاً (مكرر).'], 422);
            }
            throw $e;
        }

        app(AuditLogger::class)->log('bay.created', Bay::class, $bay->id, [], $bay->toArray());

        return response()->json(['data' => $bay, 'message' => 'تم إنشاء منطقة العمل.'], 201);
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

        $current = (string) $bay->status;
        $target  = (string) $data['status'];
        $allowedTransitions = [
            'available'      => ['available', 'reserved', 'in_use', 'maintenance', 'out_of_service'],
            'reserved'       => ['reserved', 'available', 'in_use', 'maintenance', 'out_of_service'],
            'in_use'         => ['in_use', 'available', 'maintenance', 'out_of_service'],
            'maintenance'    => ['maintenance', 'available', 'out_of_service'],
            'out_of_service' => ['out_of_service', 'maintenance', 'available'],
        ];
        $allowed = $allowedTransitions[$current] ?? [];
        if ($target !== $current && ! in_array($target, $allowed, true)) {
            return response()->json([
                'message'  => "Bay status transition {$current} -> {$target} is not allowed.",
                'code'     => 'TRANSITION_NOT_ALLOWED',
                'status'   => 409,
                'trace_id' => app('trace_id'),
            ], 409);
        }

        $bay->update($data);
        app(AuditLogger::class)->change($bay, 'bay.status_changed', $before, ['status' => $bay->status]);

        return response()->json([
            'data' => $bay->fresh(),
            'message' => 'تم تحديث حالة منطقة العمل.',
            'trace_id' => app('trace_id'),
        ]);
    }

    // ── Bookings ──────────────────────────────────────────────────────

    public function listBookings(Request $request): JsonResponse
    {
        if (! $this->bookingsEnabled($request)) {
            return response()->json(['message' => 'Bookings are disabled by configuration.', 'trace_id' => app('trace_id')], 403);
        }

        $user = $request->user();
        $per  = min(200, max(10, (int) $request->query('per_page', 30)));

        $bookings = Booking::where('company_id', $user->company_id)
            ->when($request->query('date'), fn ($q, $v) => $q->whereDate('starts_at', $v))
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->query('bay_id'), fn ($q, $v) => $q->where('bay_id', $v))
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', (int) $request->query('branch_id')))
            ->with(['bay:id,code,name', 'customer:id,name', 'vehicle:id,plate_number'])
            ->orderBy('starts_at')
            ->paginate($per);

        return response()->json($bookings);
    }

    public function storeBooking(Request $request): JsonResponse
    {
        if (! $this->bookingsEnabled($request)) {
            return response()->json(['message' => 'Bookings are disabled by configuration.', 'trace_id' => app('trace_id')], 403);
        }

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
        if (! $this->bookingsEnabled($request)) {
            return response()->json(['message' => 'Bookings are disabled by configuration.', 'trace_id' => app('trace_id')], 403);
        }

        $user    = $request->user();
        $booking = Booking::where('company_id', $user->company_id)->findOrFail($id);
        $request->validate([
            'action' => 'nullable|string|in:confirm,start,complete,cancel',
            'status' => 'nullable|string|in:confirmed,in_progress,completed,cancelled',
            'reason' => 'nullable|string|max:2000',
        ]);

        if ($request->filled('action') && $request->filled('status')) {
            return response()->json([
                'message'  => 'Send either action or status, not both.',
                'trace_id' => app('trace_id'),
            ], 422);
        }

        $resolvedAction = $request->input('action');
        if (! is_string($resolvedAction) || $resolvedAction === '') {
            if ($request->filled('status')) {
                $resolvedAction = match ((string) $request->input('status')) {
                    'confirmed'   => 'confirm',
                    'in_progress' => 'start',
                    'completed'   => 'complete',
                    'cancelled'   => 'cancel',
                    default       => null,
                };
            }
        }

        if (! is_string($resolvedAction) || $resolvedAction === '' || ! in_array($resolvedAction, ['confirm', 'start', 'complete', 'cancel'], true)) {
            return response()->json([
                'message'  => 'Booking update requires action (confirm, start, complete, cancel) or a target status (confirmed, in_progress, completed, cancelled).',
                'trace_id' => app('trace_id'),
            ], 422);
        }

        $action = $resolvedAction;
        $svc    = app(BookingService::class);

        $current = (string) $booking->status;

        if ($action === 'confirm') {
            if ($current === 'confirmed') {
                $result = $booking->fresh();
            } elseif ($current === 'pending') {
                $result = $svc->confirm($id);
            } else {
                return response()->json([
                    'message'  => "Booking status transition {$current} -> confirm is not allowed.",
                    'code'     => 'TRANSITION_NOT_ALLOWED',
                    'status'   => 409,
                    'trace_id' => app('trace_id'),
                ], 409);
            }
        } elseif ($action === 'start') {
            if ($current === 'in_progress') {
                $result = $booking->fresh();
            } elseif ($current === 'confirmed') {
                $result = $svc->start($id);
            } else {
                return response()->json([
                    'message'  => "Booking status transition {$current} -> start is not allowed.",
                    'code'     => 'TRANSITION_NOT_ALLOWED',
                    'status'   => 409,
                    'trace_id' => app('trace_id'),
                ], 409);
            }
        } elseif ($action === 'complete') {
            if ($current === 'completed') {
                $result = $booking->fresh();
            } elseif ($current === 'in_progress') {
                $result = $svc->complete($id);
            } else {
                return response()->json([
                    'message'  => "Booking status transition {$current} -> complete is not allowed.",
                    'code'     => 'TRANSITION_NOT_ALLOWED',
                    'status'   => 409,
                    'trace_id' => app('trace_id'),
                ], 409);
            }
        } else { // cancel
            if ($current === 'cancelled') {
                $result = $booking->fresh();
            } elseif (in_array($current, ['pending', 'confirmed', 'in_progress'], true)) {
                $result = $svc->cancel($id, $user->id, (string) $request->input('reason', ''));
            } else {
                return response()->json([
                    'message'  => "Booking status transition {$current} -> cancel is not allowed.",
                    'code'     => 'TRANSITION_NOT_ALLOWED',
                    'status'   => 409,
                    'trace_id' => app('trace_id'),
                ], 409);
            }
        }

        return response()->json([
            'data' => $result,
            'message' => 'تم التحديث.',
            'trace_id' => app('trace_id'),
        ]);
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

        $branch = Branch::query()
            ->where('company_id', $user->company_id)
            ->where('id', $data['branch_id'])
            ->first();

        if (! $branch) {
            return response()->json([
                'available' => false,
                'bay'       => null,
                'reason'    => 'branch_not_found',
            ]);
        }

        $outsideHours = ! BranchOpeningHours::slotAllowed($branch->opening_hours, $start, $end);

        $bay = $outsideHours
            ? null
            : app(BookingService::class)->findAvailableBay(
                $user->company_id,
                $data['branch_id'],
                $start,
                $end,
                $data['capability'] ?? null
            );

        return response()->json([
            'available' => (bool) $bay,
            'bay'       => $bay,
            'reason'    => $outsideHours ? 'outside_hours' : ($bay ? null : 'no_work_area'),
        ]);
    }

    public function heatmap(Request $request): JsonResponse
    {
        $user   = $request->user();
        $date   = $request->query('date', today()->toDateString());
        $branch = $request->query('branch_id', $user->branch_id);

        if ($branch === null || $branch === '') {
            $branch = Branch::query()
                ->where('company_id', $user->company_id)
                ->orderBy('id')
                ->value('id');
        }

        if ($branch === null) {
            return response()->json([
                'data' => [],
                'date' => $date,
                'meta' => ['branch_id' => null, 'hint' => 'no_branch_for_company'],
            ]);
        }

        $branchId = (int) $branch;
        $data = app(BookingService::class)->heatmap($user->company_id, $branchId, $date);

        return response()->json([
            'data' => $data,
            'date' => $date,
            'meta' => ['branch_id' => $branchId],
        ]);
    }

    private function bookingsEnabled(Request $request): bool
    {
        $user = $request->user();
        $vertical = Company::query()->where('id', $user->company_id)->value('vertical_profile_code');

        return $this->configResolver->resolveBool('bookings.enabled', [
            'plan' => null,
            'vertical' => $vertical,
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
        ], true);
    }
}
