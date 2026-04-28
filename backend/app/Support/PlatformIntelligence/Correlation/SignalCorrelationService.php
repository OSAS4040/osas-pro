<?php

declare(strict_types=1);

namespace App\Support\PlatformIntelligence\Correlation;

use App\Support\PlatformIntelligence\Enums\PlatformSignalSourceType;
use App\Support\PlatformIntelligence\SignalEngine\Draft\SignalDraft;

/**
 * Adds correlation keys to prepare Incident Candidate grouping (no incidents created here).
 */
final class SignalCorrelationService
{
    /**
     * @param  list<SignalDraft>  $drafts
     * @return list<SignalDraft>
     */
    public function apply(array $drafts): array
    {
        $out = [];
        foreach ($drafts as $d) {
            $keys = $d->correlation_keys;
            if ($d->affected_scope === 'platform:tenants' || str_contains($d->draft_key, 'tenant.')) {
                $keys[] = 'corr:tenant_activity_aggregate';
            }
            if ($d->source === PlatformSignalSourceType::System
                || str_contains($d->draft_key, 'system.')) {
                $keys[] = 'corr:platform_runtime_system';
            }
            if ($d->draft_key === 'sig.platform.operations.attention_queue_depth') {
                $keys[] = 'corr:operator_attention_depth';
            }
            if ($d->draft_key === 'sig.platform.adoption.trial_expiry_window') {
                $keys[] = 'corr:subscription_trial_window';
            }
            $keys = array_values(array_unique($keys));
            $out[] = new SignalDraft(
                $d->draft_key,
                $d->signal_type,
                $d->source,
                $d->title,
                $d->summary_stub,
                $d->why_stub,
                $d->affected_scope,
                $d->affected_entities,
                $d->affected_company_ids,
                $keys,
                $d->evidence,
                $d->source_ref,
                $d->observed_at,
            );
        }

        return $out;
    }
}
