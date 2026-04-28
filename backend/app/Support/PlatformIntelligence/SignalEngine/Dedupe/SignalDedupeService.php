<?php

declare(strict_types=1);

namespace App\Support\PlatformIntelligence\SignalEngine\Dedupe;

use App\Support\PlatformIntelligence\Contracts\PlatformSignalContract;
use App\Support\PlatformIntelligence\Enums\PlatformIntelligenceSeverity;

/**
 * Near-duplicate suppression: merges overlapping tenant-activity signals with high company overlap.
 */
final class SignalDedupeService
{
    /**
     * @param  list<PlatformSignalContract>  $signals
     * @return list<PlatformSignalContract>
     */
    public function dedupe(array $signals): array
    {
        if ($signals === []) {
            return [];
        }

        /** @var array<string, PlatformSignalContract> $byKey */
        $byKey = [];
        foreach ($signals as $s) {
            $fp = $this->fingerprint($s);
            if (! isset($byKey[$fp])) {
                $byKey[$fp] = $s;

                continue;
            }
            $byKey[$fp] = $this->merge($byKey[$fp], $s);
        }

        $merged = array_values($byKey);
        usort($merged, static fn (PlatformSignalContract $a, PlatformSignalContract $b) => $b->severity->value <=> $a->severity->value);

        return $this->collapseTenantOverlap($merged);
    }

    private function fingerprint(PlatformSignalContract $s): string
    {
        $ids = $s->affected_companies;
        sort($ids);

        return sha1($s->signal_type->value.'|'.$s->source->value.'|'.$s->signal_key.'|'.implode(',', $ids));
    }

    private function merge(PlatformSignalContract $a, PlatformSignalContract $b): PlatformSignalContract
    {
        $companies = array_values(array_unique([...$a->affected_companies, ...$b->affected_companies]));
        $entities = array_values(array_unique([...$a->affected_entities, ...$b->affected_entities]));
        $corr = array_values(array_unique([...$a->correlation_keys, ...$b->correlation_keys]));
        $conf = min(0.96, max($a->confidence, $b->confidence) + 0.02);
        $first = $a->first_seen_at <= $b->first_seen_at ? $a->first_seen_at : $b->first_seen_at;
        $last = $a->last_seen_at >= $b->last_seen_at ? $a->last_seen_at : $b->last_seen_at;

        return new PlatformSignalContract(
            signal_key: $a->signal_key,
            signal_type: $a->signal_type,
            title: $a->title,
            summary: $a->summary,
            why_summary: $a->why_summary,
            severity: $this->strongerSeverity($a->severity, $b->severity),
            confidence: $conf,
            source: $a->source,
            source_ref: $a->source_ref,
            affected_scope: $a->affected_scope,
            affected_entities: $entities,
            affected_companies: array_slice($companies, 0, 40),
            first_seen_at: $first,
            last_seen_at: $last,
            recommended_next_step: $a->recommended_next_step,
            correlation_keys: $corr,
            trace_id: $a->trace_id,
            correlation_id: $a->correlation_id,
        );
    }

    /**
     * Collapse inactive cluster vs low activity when >60% company overlap (near-duplicate noise).
     *
     * @param  list<PlatformSignalContract>  $signals
     * @return list<PlatformSignalContract>
     */
    private function collapseTenantOverlap(array $signals): array
    {
        $inactive = null;
        $low = null;
        $rest = [];
        foreach ($signals as $s) {
            if ($s->signal_key === 'sig.platform.tenant.inactive_cluster') {
                $inactive = $s;
            } elseif ($s->signal_key === 'sig.platform.tenant.low_activity_cluster') {
                $low = $s;
            } else {
                $rest[] = $s;
            }
        }
        if ($inactive !== null && $low !== null) {
            $overlap = $this->overlapRatio($inactive->affected_companies, $low->affected_companies);
            if ($overlap >= 0.6 && count($inactive->affected_companies) > 0) {
                $merged = $this->merge($inactive, $low);
                $rest[] = $merged;

                return array_values($rest);
            }
        }
        if ($inactive !== null) {
            $rest[] = $inactive;
        }
        if ($low !== null) {
            $rest[] = $low;
        }

        return array_values($rest);
    }

    private function strongerSeverity(PlatformIntelligenceSeverity $a, PlatformIntelligenceSeverity $b): PlatformIntelligenceSeverity
    {
        $order = ['info' => 0, 'low' => 1, 'medium' => 2, 'high' => 3, 'critical' => 4];

        return ($order[$a->value] ?? 0) >= ($order[$b->value] ?? 0) ? $a : $b;
    }

    private function overlapRatio(array $a, array $b): float
    {
        if ($a === [] || $b === []) {
            return 0.0;
        }
        $setB = array_fill_keys(array_map('strval', $b), true);
        $hit = 0;
        foreach ($a as $x) {
            if (isset($setB[(string) $x])) {
                $hit++;
            }
        }

        return $hit / max(1, count($a));
    }
}
