<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Services\WebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * @OA\Tag(name="Webhooks", description="Webhook endpoint management")
 */
class WebhookController extends Controller
{
    public function __construct(private readonly WebhookService $webhookService) {}

    public function index(Request $request): JsonResponse
    {
        $endpoints = WebhookEndpoint::where('company_id', $request->user()->company_id)
            ->orderByDesc('id')
            ->get();

        return response()->json(['data' => $endpoints, 'trace_id' => app('trace_id')]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'url'    => 'required|url|max:500',
            'events' => 'required|array|min:1',
            'events.*' => 'required|string|max:100',
        ]);

        $user   = $request->user();
        $secret = Str::random(64);

        $endpoint = WebhookEndpoint::create([
            'uuid'                => Str::uuid(),
            'company_id'          => $user->company_id,
            'created_by_user_id'  => $user->id,
            'url'                 => $data['url'],
            'events'              => $data['events'],
            'secret_hash'         => hash('sha256', $secret),
            'is_active'           => true,
        ]);

        return response()->json([
            'data'    => $endpoint,
            'secret'  => $secret,
            'message' => 'Store this webhook secret now — it will not be shown again.',
            'trace_id' => app('trace_id'),
        ], 201);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $endpoint = WebhookEndpoint::where('company_id', $request->user()->company_id)
            ->findOrFail($id);

        $endpoint->update(['is_active' => false]);

        return response()->json(['message' => 'Webhook deactivated.', 'trace_id' => app('trace_id')]);
    }

    public function deliveries(Request $request, int $endpointId): JsonResponse
    {
        $user     = $request->user();
        $endpoint = WebhookEndpoint::where('company_id', $user->company_id)->findOrFail($endpointId);

        $deliveries = WebhookDelivery::where('webhook_endpoint_id', $endpoint->id)
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 25));

        return response()->json(['data' => $deliveries, 'trace_id' => app('trace_id')]);
    }
}
