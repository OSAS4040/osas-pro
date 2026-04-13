<?php

namespace App\Services\Intelligence\Phase2;

use Illuminate\Http\Request;

/**
 * Aggregates over domain_events — read-only.
 */
final class Phase2InsightsService
{
    public function __construct(
        private readonly Phase2DomainEventQuery $queries,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Request $request): array
    {
        [$from, $to] = $this->queries->resolveWindow($request);
        $base = $this->queries->scopedBuilder($request);
        $inWindow = (clone $base)->whereBetween('occurred_at', [$from, $to]);

        $total = (clone $inWindow)->count();

        $byEventName = (clone $inWindow)
            ->selectRaw('event_name, COUNT(*) as cnt')
            ->groupBy('event_name')
            ->orderByDesc('cnt')
            ->get()
            ->map(fn ($row) => ['event_name' => $row->event_name, 'count' => (int) $row->cnt])
            ->values()
            ->all();

        $byAggregateType = (clone $inWindow)
            ->selectRaw('aggregate_type, COUNT(*) as cnt')
            ->groupBy('aggregate_type')
            ->orderByDesc('cnt')
            ->get()
            ->map(fn ($row) => ['aggregate_type' => $row->aggregate_type, 'count' => (int) $row->cnt])
            ->values()
            ->all();

        $dailyMap = [];
        foreach ((clone $inWindow)->select(['occurred_at'])->cursor() as $ev) {
            $d = $ev->occurred_at->format('Y-m-d');
            $dailyMap[$d] = ($dailyMap[$d] ?? 0) + 1;
        }
        ksort($dailyMap);
        $daily = collect($dailyMap)
            ->map(fn (int $count, string $date) => ['date' => $date, 'count' => $count])
            ->values()
            ->all();

        $latestAt = (clone $inWindow)->max('occurred_at');
        $earliestAt = (clone $inWindow)->min('occurred_at');

        return [
            'window' => [
                'from' => $from->toIso8601String(),
                'to'   => $to->toIso8601String(),
            ],
            'totals' => [
                'events' => $total,
            ],
            'by_event_name'       => $byEventName,
            'by_aggregate_type'   => $byAggregateType,
            'daily_counts'        => $daily,
            'first_occurred_at'   => $earliestAt ? (string) $earliestAt : null,
            'last_occurred_at'    => $latestAt ? (string) $latestAt : null,
        ];
    }
}
