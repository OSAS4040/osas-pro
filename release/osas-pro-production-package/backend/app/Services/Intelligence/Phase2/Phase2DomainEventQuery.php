<?php

namespace App\Services\Intelligence\Phase2;

use App\Models\DomainEvent;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Shared read-only query scoping for Phase 2 (SELECT only).
 */
final class Phase2DomainEventQuery
{
    public function scopedBuilder(Request $request): Builder
    {
        $q = DomainEvent::query();

        $user = $request->user();
        if ($request->filled('company_id')) {
            $q->where('company_id', (int) $request->query('company_id'));
        } elseif ($user && $user->company_id) {
            $q->where('company_id', $user->company_id);
        }

        return $q;
    }

    /**
     * @return array{0: \Carbon\Carbon, 1: \Carbon\Carbon}
     */
    public function resolveWindow(Request $request): array
    {
        $to = $request->date('to') ?? now();
        $from = $request->date('from') ?? $to->copy()->subDays(30);

        return [$from->copy()->startOfDay(), $to->copy()->endOfDay()];
    }
}
