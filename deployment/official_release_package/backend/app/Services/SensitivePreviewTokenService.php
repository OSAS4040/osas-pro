<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Short-lived server-side acknowledgement that the client passed the sensitive-operation preview step.
 */
final class SensitivePreviewTokenService
{
    private const PREFIX = 'sensitive_preview:v1:';

    private const TTL_SECONDS = 600;

    public const OP_BATCH_CREATE = 'work_order_batch_create';

    public const OP_CANCELLATION_REQUEST = 'work_order_cancellation_request';

    public const OP_STATUS_TO_APPROVED = 'work_order_status_to_approved';

    /**
     * @param  list<int>  $workOrderIds
     */
    public function issue(
        int $companyId,
        int $userId,
        string $operation,
        array $workOrderIds,
        ?string $batchFingerprint,
    ): string {
        $token = bin2hex(random_bytes(24));
        $ids = array_values(array_unique(array_map('intval', $workOrderIds)));
        sort($ids);

        Cache::put($this->key($token), [
            'company_id' => $companyId,
            'user_id' => $userId,
            'operation' => $operation,
            'work_order_ids' => $ids,
            'batch_fingerprint' => $batchFingerprint,
        ], self::TTL_SECONDS);

        return $token;
    }

    /**
     * @param  list<int>|null  $expectedWorkOrderIds  null = skip check
     */
    public function assertValid(
        ?string $token,
        int $companyId,
        int $userId,
        string $operation,
        ?array $expectedWorkOrderIds,
        ?string $batchFingerprint,
    ): void {
        if ($token === null || trim($token) === '') {
            throw new \DomainException('مطلوب المرور بنافذة مراجعة العملية الحساسة (preview) قبل التنفيذ.');
        }

        $payload = Cache::get($this->key($token));
        if (! is_array($payload)) {
            throw new \DomainException('رمز المراجعة منتهٍ أو غير صالح. أعد فتح نافذة المراجعة.');
        }

        if ((int) ($payload['company_id'] ?? 0) !== $companyId) {
            throw new \DomainException('رمز المراجعة لا يطابق الشركة الحالية.');
        }

        if ((int) ($payload['user_id'] ?? 0) !== $userId) {
            throw new \DomainException('رمز المراجعة لا يطابق المستخدم الحالي.');
        }

        if (($payload['operation'] ?? '') !== $operation) {
            throw new \DomainException('نوع العملية لا يطابق المراجعة الأخيرة.');
        }

        if ($expectedWorkOrderIds !== null) {
            $exp = array_values(array_unique(array_map('intval', $expectedWorkOrderIds)));
            sort($exp);
            $got = $payload['work_order_ids'] ?? [];
            if (! is_array($got)) {
                $got = [];
            }
            $got = array_values(array_unique(array_map('intval', $got)));
            sort($got);
            if ($exp !== $got) {
                throw new \DomainException('أوامر العمل المستهدفة تغيّرت عن معاينة المراجعة.');
            }
        }

        if ($operation === self::OP_BATCH_CREATE) {
            $fp = $payload['batch_fingerprint'] ?? null;
            if (! is_string($fp) || $fp === '') {
                throw new \DomainException('بصمة الدفعة مفقودة في المراجعة.');
            }
            if ($batchFingerprint === null || $batchFingerprint === '' || ! hash_equals($fp, $batchFingerprint)) {
                throw new \DomainException('محتوى الدفعة لا يطابق المراجعة — أعد المعاينة.');
            }
        }
    }

    public function invalidate(?string $token): void
    {
        if ($token === null || trim($token) === '') {
            return;
        }
        Cache::forget($this->key($token));
    }

    public static function fingerprintBatchLines(array $lines): string
    {
        $normalized = [];
        foreach ($lines as $line) {
            if (! is_array($line)) {
                continue;
            }
            $normalized[] = [
                'customer_id' => (int) ($line['customer_id'] ?? 0),
                'vehicle_id' => (int) ($line['vehicle_id'] ?? 0),
                'items' => $line['items'] ?? [],
            ];
        }
        usort($normalized, static function (array $a, array $b): int {
            if ($a['vehicle_id'] !== $b['vehicle_id']) {
                return $a['vehicle_id'] <=> $b['vehicle_id'];
            }

            return $a['customer_id'] <=> $b['customer_id'];
        });

        $json = json_encode($normalized, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        return hash('sha256', $json);
    }

    private function key(string $token): string
    {
        return self::PREFIX.$token;
    }
}
