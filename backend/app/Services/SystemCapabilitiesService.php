<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\User;
use App\Support\TenantBusinessFeatures;

/**
 * بناء كتالوج «قدرات النظام» لعرض آمن — بدون تسريب مسارات إدارية داخلية أو مفاتيح حساسة.
 */
final class SystemCapabilitiesService
{
    /**
     * @return array{business_type: string, items: list<array<string, mixed>>}
     */
    public function buildFor(User $user, Company $company): array
    {
        $settings  = is_array($company->settings) ? $company->settings : [];
        $profile   = is_array($settings['business_profile'] ?? null) ? $settings['business_profile'] : [];
        $bizType   = (string) ($profile['business_type'] ?? 'service_center');
        $matrix    = TenantBusinessFeatures::effectiveMatrix($company);
        $catalog   = config('system_capabilities.items', []);
        usort($catalog, fn ($a, $b) => ($a['sort'] ?? 0) <=> ($b['sort'] ?? 0));

        $items = [];
        foreach ($catalog as $row) {
            $items[] = $this->evaluateRow($row, $user, $matrix);
        }

        return [
            'business_type'       => $bizType,
            'business_type_label' => self::businessTypeLabel($bizType),
            'items'               => $items,
        ];
    }

    /**
     * @return array{ar: string, en: string}
     */
    public static function businessTypeLabel(string $code): array
    {
        return match ($code) {
            'service_center' => ['ar' => 'مركز خدمة / منفذ بيع', 'en' => 'Service center / retail outlet'],
            'retail' => ['ar' => 'تجزئة', 'en' => 'Retail'],
            'fleet_operator' => ['ar' => 'مشغّل أسطول', 'en' => 'Fleet operator'],
            default => ['ar' => 'مركز خدمة', 'en' => 'Service center'],
        };
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, bool>  $matrix
     * @return array<string, mixed>
     */
    private function evaluateRow(array $row, User $user, array $matrix): array
    {
        $id = (string) ($row['id'] ?? '');
        $rollout = (string) ($row['rollout'] ?? 'live');
        $pathIn = isset($row['path']) && is_string($row['path']) ? $row['path'] : null;

        $base = [
            'id'          => $id,
            'section'     => $row['section'] ?? ['ar' => '', 'en' => ''],
            'title'       => $row['title'] ?? ['ar' => '', 'en' => ''],
            'description' => $row['description'] ?? ['ar' => '', 'en' => ''],
            'rollout'     => in_array($rollout, ['live', 'beta', 'planned', 'cancelled', 'post_launch'], true) ? $rollout : 'live',
        ];

        if ($rollout === 'planned') {
            return $base + [
                'status'    => 'planned',
                'path'      => null,
                'reason_ar' => 'على خارطة الطريق — غير متاح كمسار تشغيلي كامل بعد.',
                'reason_en' => 'On the roadmap — not yet available as a full operational path.',
            ];
        }

        if ($rollout === 'cancelled') {
            return $base + [
                'status'    => 'cancelled',
                'path'      => null,
                'reason_ar' => 'قرار منتج: خارج النطاق الحالي للمنتج.',
                'reason_en' => 'Product decision: out of current product scope.',
            ];
        }

        if ($rollout === 'post_launch') {
            return $base + [
                'status'    => 'post_launch',
                'path'      => null,
                'reason_ar' => 'مرحلة لاحقة بعد نسخة النشر والاستقرار التشغيلي.',
                'reason_en' => 'Planned for a later phase after initial release and operational stability.',
            ];
        }

        $gate = isset($row['feature_gate']) && is_string($row['feature_gate']) ? $row['feature_gate'] : null;
        if ($gate !== null && ($matrix[$gate] ?? false) !== true) {
            return $base + [
                'status'    => 'restricted_activity',
                'path'      => null,
                'reason_ar' => 'غير مفعّل لنشاط منشأتك أو إعدادات الباقة/الملف التشغيلي.',
                'reason_en' => 'Not enabled for your company profile or subscription mix.',
                'gate'      => $gate,
            ];
        }

        $requiresManager = (bool) ($row['requires_manager'] ?? false);
        if ($requiresManager && ! $this->isTenantAdmin($user)) {
            return $base + [
                'status'    => 'restricted_role',
                'path'      => null,
                'reason_ar' => 'يتطلب دور إداري داخل المنشأة.',
                'reason_en' => 'Requires a managerial role within the tenant.',
            ];
        }

        $permission = isset($row['permission']) && is_string($row['permission']) ? $row['permission'] : null;
        if ($permission !== null && ! $this->userCan($user, $permission)) {
            return $base + [
                'status'    => 'restricted_permission',
                'path'      => null,
                'reason_ar' => 'صلاحية دورك الحالي لا تكفي لفتح هذا المسار.',
                'reason_en' => 'Your current role permissions do not include this area.',
            ];
        }

        $path = $pathIn;
        if ($path !== null && $path !== '' && ! str_starts_with($path, '/')) {
            $path = '/'.$path;
        }

        $status = $rollout === 'beta' ? 'beta' : 'available';

        $out = $base + [
            'status' => $status,
            'path'   => $path,
        ];
        if ($status === 'beta') {
            $out['reason_ar'] = 'قد تتغير واجهة أو بناء المنتج — تحقق مع مشرف النظام.';
            $out['reason_en'] = 'UI or build flags may apply — confirm with your administrator.';
        }

        return $out;
    }

    private function isTenantAdmin(User $user): bool
    {
        $role = $user->role instanceof UserRole ? $user->role : UserRole::tryFrom((string) $user->role);

        return $role !== null && $role->isAdmin();
    }

    private function userCan(User $user, string $permission): bool
    {
        $role = $user->role instanceof UserRole ? $user->role->value : (string) $user->role;
        if ($role === UserRole::Owner->value) {
            return true;
        }
        if (in_array($role, [UserRole::Manager->value], true) && str_starts_with($permission, 'reports.')) {
            return true;
        }
        $configPerms = config('permissions.roles.'.$role, []);
        if (in_array('*', $configPerms, true)) {
            return true;
        }

        return in_array($permission, $configPerms, true);
    }
}
