<?php

declare(strict_types=1);

namespace App\Support\StaffNav;

/**
 * مفاتيح مستقرة لعناصر قائمة فريق العمل — تُخزَّن في platform_tenant_nav_hides.nav_key
 * ويُشتق المفتاح من المسار بنفس منطق الواجهة (pathToStaffNavKey).
 */
final class StaffNavKey
{
    public static function forStaffHref(string $href): string
    {
        $s = explode('?', $href, 2)[0];
        $parts = explode('#', $s, 2);
        $path = $parts[0] === '' ? '/' : $parts[0];
        $hash = $parts[1] ?? '';

        $slug = $path === '/' ? 'dashboard' : trim(str_replace('/', '_', ltrim($path, '_')), '_');
        $slug = preg_replace('/[^a-zA-Z0-9_]+/', '_', $slug) ?? $slug;
        $slug = trim((string) $slug, '_');

        if ($hash !== '') {
            $h = preg_replace('/[^a-zA-Z0-9_]+/', '_', $hash) ?? $hash;
            $h = trim((string) $h, '_');
            if ($h !== '') {
                $slug .= '_'.$h;
            }
        }

        if ($slug === '') {
            $slug = 'dashboard';
        }

        return 'staff.nav.'.$slug;
    }

    public static function forCustomerHref(string $href): string
    {
        $path = explode('?', $href, 2)[0];
        $path = preg_replace('#^/customer/?#', '', $path) ?? '';
        $slug = $path === '' || $path === '/' ? 'dashboard' : trim(str_replace('/', '_', $path), '_');
        $slug = preg_replace('/[^a-zA-Z0-9_]+/', '_', $slug) ?? $slug;
        $slug = trim((string) $slug, '_');

        return 'customer.nav.'.($slug === '' ? 'dashboard' : $slug);
    }

    public static function isValidStaffKey(string $key): bool
    {
        return str_starts_with($key, 'staff.nav.') && strlen($key) < 200;
    }

    public static function isValidCustomerKey(string $key): bool
    {
        return str_starts_with($key, 'customer.nav.') && strlen($key) < 200;
    }
}
