<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Read-only helpers for domain generalization labels and route prefixes.
 */
final class Domain
{
    public static function getEntityName(string $legacyKey): string
    {
        $entity = self::entity($legacyKey);
        if ($entity === null) {
            return self::titleFallback($legacyKey);
        }

        if (config('domain_mapping.use_canonical_labels', false)) {
            return self::canonicalSingular((string) ($entity['canonical'] ?? ''));
        }

        return self::resolveLabel($entity, 'label');
    }

    public static function getPlural(string $legacyKey): string
    {
        $entity = self::entity($legacyKey);
        if ($entity === null) {
            return self::titleFallback($legacyKey).'s';
        }

        if (config('domain_mapping.use_canonical_labels', false)) {
            return self::canonicalPlural((string) ($entity['canonical'] ?? ''));
        }

        return self::resolveLabel($entity, 'plural');
    }

    /**
     * Stable route prefix segment (matches existing router).
     */
    public static function getRoutePrefix(string $legacyKey): string
    {
        $entity = self::entity($legacyKey);

        if ($entity === null || empty($entity['route_prefix'])) {
            return self::legacyKeyToRouteSegment($legacyKey);
        }

        return (string) $entity['route_prefix'];
    }

    public static function getCanonicalKey(string $legacyKey): ?string
    {
        $entity = self::entity($legacyKey);

        if (is_array($entity) && isset($entity['canonical'])) {
            return (string) $entity['canonical'];
        }

        return config('domain_mapping.map.'.$legacyKey);
    }

    public static function usesCanonicalLabels(): bool
    {
        return (bool) config('domain_mapping.use_canonical_labels', false);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function entity(string $legacyKey): ?array
    {
        $entities = config('domain_mapping.entities', []);

        $entity = $entities[$legacyKey] ?? null;

        return is_array($entity) ? $entity : null;
    }

    /**
     * @param  array<string, mixed>  $entity
     */
    private static function resolveLabel(array $entity, string $field): string
    {
        $value = $entity[$field] ?? null;

        if (is_string($value)) {
            return $value;
        }

        if (is_array($value)) {
            $locale = self::resolveLocale();

            return $value[$locale] ?? $value['en'] ?? '';
        }

        return '';
    }

    private static function canonicalSingular(string $canonical): string
    {
        return Str::headline(str_replace('_', ' ', $canonical));
    }

    private static function canonicalPlural(string $canonical): string
    {
        return Str::plural(self::canonicalSingular($canonical));
    }

    private static function resolveLocale(): string
    {
        $locale = app()->getLocale();

        return in_array($locale, ['en', 'ar'], true) ? $locale : 'en';
    }

    private static function titleFallback(string $legacyKey): string
    {
        return ucfirst(str_replace('_', ' ', $legacyKey));
    }

    private static function legacyKeyToRouteSegment(string $legacyKey): string
    {
        return str_replace('_', '-', $legacyKey);
    }
}
