<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\SubscriptionsV2;

use App\Http\Controllers\Controller;
use App\Http\Requests\SubscriptionsV2\ImportBankTransactionsRequest;
use App\Modules\SubscriptionsV2\Services\BankTransactionImportService;
use Illuminate\Http\JsonResponse;

final class PlatformBankTransactionImportController extends Controller
{
    public function store(ImportBankTransactionsRequest $request, BankTransactionImportService $service): JsonResponse
    {
        $ids = $service->import(
            $request->validated('rows'),
            (int) $request->user()->id,
        );

        return response()->json(['data' => ['inserted_ids' => $ids, 'count' => count($ids)], 'trace_id' => app('trace_id')], 201);
    }
}
