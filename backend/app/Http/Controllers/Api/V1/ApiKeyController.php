<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ApiKeyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $keys = ApiKey::where('company_id', $request->user()->company_id)
            ->whereNull('revoked_at')
            ->get(['key_id', 'name', 'permissions_scope', 'rate_limit', 'expires_at', 'created_at']);

        return response()->json(['data' => $keys, 'trace_id' => app('trace_id')]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'               => 'required|string|max:255',
            'permissions_scope'  => 'nullable|array',
            'rate_limit'         => 'nullable|integer|min:1',
            'expires_at'         => 'nullable|date|after:now',
        ]);

        $rawSecret = Str::random(64);

        $key = ApiKey::create([
            'key_id'             => Str::uuid(),
            'company_id'         => $request->user()->company_id,
            'created_by_user_id' => $request->user()->id,
            'name'               => $data['name'],
            'secret_hash'        => hash('sha256', $rawSecret),
            'permissions_scope'  => $data['permissions_scope'] ?? null,
            'rate_limit'         => $data['rate_limit'] ?? 1000,
            'expires_at'         => $data['expires_at'] ?? null,
        ]);

        return response()->json([
            'data'       => $key->only(['key_id', 'name', 'expires_at', 'created_at']),
            'secret'     => $rawSecret,
            'message'    => 'Store this secret now — it will not be shown again.',
            'trace_id'   => app('trace_id'),
        ], 201);
    }

    public function revoke(Request $request, string $id): JsonResponse
    {
        ApiKey::where('company_id', $request->user()->company_id)
            ->where('key_id', $id)
            ->whereNull('revoked_at')
            ->firstOrFail()
            ->update(['revoked_at' => now()]);

        return response()->json(['message' => 'API key revoked.', 'trace_id' => app('trace_id')]);
    }
}
