<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminCommunicationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $items = $this->communicationsBucket($company)['transactions'] ?? [];

        $tab = (string) $request->query('tab', 'inbox');
        $q = mb_strtolower(trim((string) $request->query('q', '')));
        $status = trim((string) $request->query('status', ''));

        $filtered = array_values(array_filter($items, function (array $item) use ($tab, $q, $status): bool {
            $state = (string) ($item['state'] ?? 'draft');
            $destination = (string) ($item['destination'] ?? '');
            $assignedTo = (string) ($item['assigned_to'] ?? '');
            $archived = (bool) ($item['archived'] ?? false);

            $inTab = match ($tab) {
                'outbox' => in_array($state, ['submitted', 'under_review', 'signed', 'sent'], true) && !$archived,
                'assigned' => $assignedTo !== '' && !$archived,
                'archived' => $archived,
                default => in_array($state, ['submitted', 'under_review', 'signed', 'sent', 'returned', 'rejected'], true) && $destination !== '' && !$archived,
            };
            if (!$inTab) {
                return false;
            }

            if ($status !== '' && $state !== $status) {
                return false;
            }

            if ($q === '') {
                return true;
            }

            $haystack = mb_strtolower(implode(' ', [
                (string) ($item['reference'] ?? ''),
                (string) ($item['subject'] ?? ''),
                (string) ($item['origin'] ?? ''),
                (string) ($item['destination'] ?? ''),
                (string) ($item['category'] ?? ''),
            ]));

            return str_contains($haystack, $q);
        }));

        usort($filtered, fn(array $a, array $b) => strcmp((string) ($b['updated_at'] ?? ''), (string) ($a['updated_at'] ?? '')));

        return response()->json([
            'data' => $filtered,
            'meta' => ['total' => count($filtered)],
            'trace_id' => app('trace_id'),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request, true);
        $payload = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:10000'],
            'category' => ['nullable', 'string', 'max:80'],
            'priority' => ['nullable', 'string', 'in:low,normal,high,critical'],
            'confidentiality' => ['nullable', 'string', 'in:public,internal,confidential,strictly_confidential'],
            'origin' => ['nullable', 'string', 'max:120'],
            'destination' => ['nullable', 'string', 'max:120'],
            'assigned_to' => ['nullable', 'string', 'max:120'],
            'due_date' => ['nullable', 'date'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:40'],
        ]);

        $bucket = $this->communicationsBucket($company);
        $transactions = $bucket['transactions'] ?? [];
        $now = now()->toIso8601String();
        $id = (string) Str::uuid();
        $reference = $this->nextReference($transactions);

        $record = [
            'id' => $id,
            'reference' => $reference,
            'subject' => trim($payload['subject']),
            'body' => trim((string) ($payload['body'] ?? '')),
            'category' => (string) ($payload['category'] ?? 'عام'),
            'priority' => (string) ($payload['priority'] ?? 'normal'),
            'confidentiality' => (string) ($payload['confidentiality'] ?? 'internal'),
            'origin' => (string) ($payload['origin'] ?? 'الإدارة'),
            'destination' => (string) ($payload['destination'] ?? ''),
            'assigned_to' => (string) ($payload['assigned_to'] ?? ''),
            'due_date' => $payload['due_date'] ?? null,
            'tags' => array_values(array_filter((array) ($payload['tags'] ?? []), fn($t) => trim((string) $t) !== '')),
            'state' => 'draft',
            'archived' => false,
            'signature' => [
                'required' => false,
                'ordered' => false,
                'deadline' => null,
                'requested_signers' => [],
                'signed_by' => [],
                'status' => 'not_requested',
            ],
            'transfer' => [
                'last_to' => null,
                'reason' => null,
                'deadline' => null,
            ],
            'archive' => [
                'box_code' => null,
                'folder_code' => null,
                'retention_months' => null,
                'archived_at' => null,
                'archived_by' => null,
            ],
            'timeline' => [[
                'at' => $now,
                'actor' => (string) ($request->user()?->name ?? 'system'),
                'action' => 'created',
                'note' => 'تم إنشاء المعاملة كمسودة',
            ]],
            'created_at' => $now,
            'updated_at' => $now,
        ];

        array_unshift($transactions, $record);
        $bucket['transactions'] = $transactions;
        $this->persistBucket($company, $bucket);

        return response()->json(['data' => $record, 'trace_id' => app('trace_id')], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $company = $this->resolveCompany($request, true);
        $payload = $request->validate([
            'subject' => ['sometimes', 'string', 'max:255'],
            'body' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'category' => ['sometimes', 'string', 'max:80'],
            'priority' => ['sometimes', 'string', 'in:low,normal,high,critical'],
            'confidentiality' => ['sometimes', 'string', 'in:public,internal,confidential,strictly_confidential'],
            'origin' => ['sometimes', 'string', 'max:120'],
            'destination' => ['sometimes', 'string', 'max:120'],
            'assigned_to' => ['sometimes', 'string', 'max:120'],
            'due_date' => ['sometimes', 'nullable', 'date'],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['string', 'max:40'],
        ]);

        $updated = $this->mutateRecord($company, $id, function (array &$item) use ($payload, $request): void {
            if (($item['state'] ?? 'draft') !== 'draft') {
                return;
            }
            foreach (['subject', 'body', 'category', 'priority', 'confidentiality', 'origin', 'destination', 'assigned_to', 'due_date', 'tags'] as $key) {
                if (array_key_exists($key, $payload)) {
                    $item[$key] = $payload[$key];
                }
            }
            $this->pushTimeline($item, (string) ($request->user()?->name ?? 'system'), 'updated', 'تم تحديث بيانات المعاملة');
        });

        return response()->json(['data' => $updated, 'trace_id' => app('trace_id')]);
    }

    public function submit(Request $request, string $id): JsonResponse
    {
        $company = $this->resolveCompany($request, true);
        $updated = $this->mutateRecord($company, $id, function (array &$item) use ($request): void {
            $item['state'] = 'submitted';
            $this->pushTimeline($item, (string) ($request->user()?->name ?? 'system'), 'submitted', 'تم إرسال المعاملة للمراجعة');
        });
        return response()->json(['data' => $updated, 'trace_id' => app('trace_id')]);
    }

    public function transfer(Request $request, string $id): JsonResponse
    {
        $company = $this->resolveCompany($request, true);
        $payload = $request->validate([
            'to' => ['required', 'string', 'max:120'],
            'reason' => ['required', 'string', 'max:500'],
            'deadline' => ['nullable', 'date'],
        ]);

        $updated = $this->mutateRecord($company, $id, function (array &$item) use ($payload, $request): void {
            $item['destination'] = $payload['to'];
            $item['transfer'] = [
                'last_to' => $payload['to'],
                'reason' => $payload['reason'],
                'deadline' => $payload['deadline'] ?? null,
            ];
            $item['state'] = 'under_review';
            $this->pushTimeline($item, (string) ($request->user()?->name ?? 'system'), 'transferred', "تم تحويل المعاملة إلى {$payload['to']}");
        });

        return response()->json(['data' => $updated, 'trace_id' => app('trace_id')]);
    }

    public function requestSignature(Request $request, string $id): JsonResponse
    {
        $company = $this->resolveCompany($request, true);
        $payload = $request->validate([
            'signers' => ['required', 'array', 'min:1'],
            'signers.*' => ['string', 'max:120'],
            'ordered' => ['nullable', 'boolean'],
            'deadline' => ['nullable', 'date'],
        ]);

        $updated = $this->mutateRecord($company, $id, function (array &$item) use ($payload, $request): void {
            $item['signature'] = [
                'required' => true,
                'ordered' => (bool) ($payload['ordered'] ?? false),
                'deadline' => $payload['deadline'] ?? null,
                'requested_signers' => array_values($payload['signers']),
                'signed_by' => (array) ($item['signature']['signed_by'] ?? []),
                'status' => 'pending',
            ];
            $item['state'] = 'under_review';
            $this->pushTimeline($item, (string) ($request->user()?->name ?? 'system'), 'signature_requested', 'تم طلب التوقيع على المعاملة');
        });

        return response()->json(['data' => $updated, 'trace_id' => app('trace_id')]);
    }

    public function sign(Request $request, string $id): JsonResponse
    {
        $company = $this->resolveCompany($request, true);
        $payload = $request->validate([
            'signer' => ['required', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $updated = $this->mutateRecord($company, $id, function (array &$item) use ($payload, $request): void {
            $signed = (array) ($item['signature']['signed_by'] ?? []);
            if (!in_array($payload['signer'], $signed, true)) {
                $signed[] = $payload['signer'];
            }
            $requested = (array) ($item['signature']['requested_signers'] ?? []);
            $allSigned = !empty($requested) && count(array_intersect($requested, $signed)) >= count($requested);

            $item['signature']['signed_by'] = $signed;
            $item['signature']['status'] = $allSigned ? 'completed' : 'pending';
            $item['state'] = $allSigned ? 'signed' : 'under_review';
            $this->pushTimeline(
                $item,
                (string) ($request->user()?->name ?? 'system'),
                'signed',
                'تم اعتماد المعاملة من ' . $payload['signer'] . (($payload['note'] ?? '') ? (' - ' . $payload['note']) : '')
            );
        });

        return response()->json(['data' => $updated, 'trace_id' => app('trace_id')]);
    }

    public function archive(Request $request, string $id): JsonResponse
    {
        $company = $this->resolveCompany($request, true);
        $payload = $request->validate([
            'box_code' => ['nullable', 'string', 'max:100'],
            'folder_code' => ['nullable', 'string', 'max:100'],
            'retention_months' => ['nullable', 'integer', 'min:1', 'max:240'],
        ]);

        $updated = $this->mutateRecord($company, $id, function (array &$item) use ($payload, $request): void {
            $item['archived'] = true;
            $item['state'] = 'archived';
            $item['archive'] = [
                'box_code' => $payload['box_code'] ?? null,
                'folder_code' => $payload['folder_code'] ?? null,
                'retention_months' => $payload['retention_months'] ?? null,
                'archived_at' => now()->toIso8601String(),
                'archived_by' => (string) ($request->user()?->name ?? 'system'),
            ];
            $this->pushTimeline($item, (string) ($request->user()?->name ?? 'system'), 'archived', 'تمت أرشفة المعاملة');
        });

        return response()->json(['data' => $updated, 'trace_id' => app('trace_id')]);
    }

    public function restore(Request $request, string $id): JsonResponse
    {
        $company = $this->resolveCompany($request, true);
        $updated = $this->mutateRecord($company, $id, function (array &$item) use ($request): void {
            $item['archived'] = false;
            $item['state'] = 'sent';
            $this->pushTimeline($item, (string) ($request->user()?->name ?? 'system'), 'restored', 'تمت إعادة المعاملة من الأرشيف');
        });

        return response()->json(['data' => $updated, 'trace_id' => app('trace_id')]);
    }

    private function resolveCompany(Request $request, bool $forUpdate = false): Company
    {
        $company = Company::findOrFail($request->user()->company_id);
        $this->authorize($forUpdate ? 'update' : 'view', $company);
        return $company;
    }

    private function communicationsBucket(Company $company): array
    {
        $settings = is_array($company->settings) ? $company->settings : [];
        $bucket = $settings['administrative_communications'] ?? [];
        $bucket['transactions'] = is_array($bucket['transactions'] ?? null) ? $bucket['transactions'] : [];
        return $bucket;
    }

    private function persistBucket(Company $company, array $bucket): void
    {
        $settings = is_array($company->settings) ? $company->settings : [];
        $settings['administrative_communications'] = $bucket;
        $company->update(['settings' => $settings]);
    }

    private function mutateRecord(Company $company, string $id, callable $mutator): array
    {
        $bucket = $this->communicationsBucket($company);
        $items = $bucket['transactions'] ?? [];
        $found = null;

        foreach ($items as &$item) {
            if ((string) ($item['id'] ?? '') !== $id) {
                continue;
            }
            $mutator($item);
            $item['updated_at'] = now()->toIso8601String();
            $found = $item;
            break;
        }

        if ($found === null) {
            abort(404, 'المعاملة غير موجودة');
        }

        $bucket['transactions'] = $items;
        $this->persistBucket($company, $bucket);
        return $found;
    }

    private function nextReference(array $transactions): string
    {
        $max = 0;
        foreach ($transactions as $item) {
            $ref = (string) ($item['reference'] ?? '');
            if (preg_match('/(?:OSAS-COM|ASASPRO-COM)-(\d+)/', $ref, $matches)) {
                $max = max($max, (int) $matches[1]);
            }
        }

        return 'ASASPRO-COM-' . str_pad((string) ($max + 1), 5, '0', STR_PAD_LEFT);
    }

    private function pushTimeline(array &$item, string $actor, string $action, string $note): void
    {
        $timeline = is_array($item['timeline'] ?? null) ? $item['timeline'] : [];
        $timeline[] = [
            'at' => now()->toIso8601String(),
            'actor' => $actor,
            'action' => $action,
            'note' => $note,
        ];
        $item['timeline'] = $timeline;
    }
}
