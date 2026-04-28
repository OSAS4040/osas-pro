<?php

declare(strict_types=1);

namespace App\Support\PlatformIntelligence\SignalEngine\Normalize;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * Validates overview payload shape and exposes typed accessors for detectors.
 */
final class OverviewSnapshotNormalizer
{
    /**
     * @param  array<string, mixed>  $overview
     */
    public function __construct(
        private readonly array $overview,
    ) {}

    public function generatedAt(): DateTimeImmutable
    {
        $raw = $this->overview['generated_at'] ?? null;
        if (is_string($raw) && $raw !== '') {
            return new DateTimeImmutable($raw);
        }

        return new DateTimeImmutable('now');
    }

    /**
     * @return array<string, int>
     */
    public function kpis(): array
    {
        $k = $this->overview['kpis'] ?? [];
        if (! is_array($k)) {
            return [];
        }
        $out = [];
        foreach ($k as $key => $val) {
            if (! is_string($key)) {
                continue;
            }
            if (is_int($val)) {
                $out[$key] = $val;
            } elseif (is_numeric($val)) {
                $out[$key] = (int) $val;
            }
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public function health(): array
    {
        $h = $this->overview['health'] ?? [];

        return is_array($h) ? $h : [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function alerts(): array
    {
        $a = $this->overview['alerts'] ?? [];

        return is_array($a) ? array_values(array_filter($a, 'is_array')) : [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function attention(): array
    {
        $a = $this->overview['companies_requiring_attention'] ?? [];

        return is_array($a) ? array_values(array_filter($a, 'is_array')) : [];
    }

    public function hasDefinitions(): bool
    {
        return is_array($this->overview['definitions'] ?? null) && $this->overview['definitions'] !== [];
    }

    public function overviewCompletenessScore(): float
    {
        $score = 0.55;
        if ($this->hasDefinitions()) {
            $score += 0.15;
        }
        if ($this->attention() !== []) {
            $score += 0.15;
        }
        if ($this->health() !== []) {
            $score += 0.15;
        }

        return min(1.0, $score);
    }

    public function toIso(DateTimeImmutable $dt): string
    {
        return $dt->format(DateTimeInterface::ATOM);
    }
}
