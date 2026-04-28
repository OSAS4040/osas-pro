<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Platform;

use App\Http\Controllers\Controller;
use App\Models\PlatformServiceProvider;
use App\Models\PlatformServiceProviderCost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class PlatformServiceProviderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = PlatformServiceProvider::query()->orderBy('name');
        if ($request->boolean('active_only')) {
            $q->where('is_active', true);
        }

        return response()->json([
            'data' => $q->paginate(min((int) $request->query('per_page', 50), 200)),
            'trace_id' => app('trace_id'),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'email' => ['nullable', 'email', 'max:255'],
            'regions' => ['nullable', 'array'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $row = PlatformServiceProvider::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => $data['name'],
            'contact_name' => $data['contact_name'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'regions' => $data['regions'] ?? null,
            'notes' => $data['notes'] ?? null,
            'is_active' => true,
        ]);

        return response()->json(['data' => $row, 'trace_id' => app('trace_id')], 201);
    }

    public function storeCost(Request $request, int $providerId): JsonResponse
    {
        $provider = PlatformServiceProvider::query()->findOrFail($providerId);
        $data = $request->validate([
            'service_code' => ['required', 'string', 'max:64'],
            'cost_amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:8'],
            'effective_from' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $cost = PlatformServiceProviderCost::query()->create([
            'platform_service_provider_id' => $provider->id,
            'service_code' => $data['service_code'],
            'cost_amount' => $data['cost_amount'],
            'currency' => $data['currency'] ?? 'SAR',
            'effective_from' => $data['effective_from'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        return response()->json(['data' => $cost, 'trace_id' => app('trace_id')], 201);
    }

    public function costs(Request $request, int $providerId): JsonResponse
    {
        PlatformServiceProvider::query()->findOrFail($providerId);
        $rows = PlatformServiceProviderCost::query()
            ->where('platform_service_provider_id', $providerId)
            ->orderBy('service_code')
            ->paginate(min((int) $request->query('per_page', 100), 500));

        return response()->json(['data' => $rows, 'trace_id' => app('trace_id')]);
    }
}
