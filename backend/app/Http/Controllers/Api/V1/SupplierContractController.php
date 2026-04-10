<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Models\SupplierContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupplierContractController extends Controller
{
    public function index(Request $request, int $supplierId): JsonResponse
    {
        Supplier::query()->findOrFail($supplierId);

        $rows = SupplierContract::query()
            ->where('supplier_id', $supplierId)
            ->with('createdBy:id,name')
            ->orderByDesc('expires_at')
            ->orderByDesc('id')
            ->get();

        return response()->json(['data' => $rows, 'trace_id' => app('trace_id')]);
    }

    public function store(Request $request, int $supplierId): JsonResponse
    {
        $supplier = Supplier::query()->findOrFail($supplierId);

        $data = $request->validate([
            'title'       => ['required', 'string', 'max:255'],
            'expires_at'  => ['nullable', 'date'],
            'notes'       => ['nullable', 'string', 'max:5000'],
            'file'        => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        $file = $request->file('file');
        $companyId = (int) $request->user()->company_id;
        $dir       = "supplier-contracts/{$companyId}";
        $path      = $file->store($dir, 'local');

        $contract = SupplierContract::create([
            'uuid'                 => Str::uuid()->toString(),
            'company_id'           => $companyId,
            'supplier_id'          => $supplier->id,
            'title'                => $data['title'],
            'stored_path'          => $path,
            'original_filename'    => $file->getClientOriginalName(),
            'mime_type'            => $file->getClientMimeType(),
            'expires_at'           => $data['expires_at'] ?? null,
            'notes'                => $data['notes'] ?? null,
            'created_by_user_id'   => $request->user()->id,
        ]);

        return response()->json(['data' => $contract, 'trace_id' => app('trace_id')], 201);
    }

    public function destroy(Request $request, int $supplierId, int $contractId): JsonResponse
    {
        Supplier::query()->findOrFail($supplierId);

        $contract = SupplierContract::query()
            ->where('supplier_id', $supplierId)
            ->findOrFail($contractId);

        if ($contract->stored_path && Storage::disk('local')->exists($contract->stored_path)) {
            Storage::disk('local')->delete($contract->stored_path);
        }

        $contract->delete();

        return response()->json(['message' => 'Deleted.', 'trace_id' => app('trace_id')]);
    }

    public function download(Request $request, int $supplierId, int $contractId): StreamedResponse|JsonResponse
    {
        Supplier::query()->findOrFail($supplierId);

        $contract = SupplierContract::query()
            ->where('supplier_id', $supplierId)
            ->findOrFail($contractId);

        if (! $contract->stored_path || ! Storage::disk('local')->exists($contract->stored_path)) {
            return response()->json(['message' => 'File not found.', 'trace_id' => app('trace_id')], 404);
        }

        return Storage::disk('local')->download(
            $contract->stored_path,
            $contract->original_filename ?: 'contract.pdf',
            ['Content-Type' => $contract->mime_type ?: 'application/pdf']
        );
    }
}
