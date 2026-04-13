<?php

declare(strict_types=1);

namespace App\Reporting;

use Carbon\CarbonImmutable;

/**
 * Immutable reporting window (inclusive day bounds in app timezone).
 */
final class ReportingDateRange
{
    public function __construct(
        public readonly CarbonImmutable $startsAt,
        public readonly CarbonImmutable $endsAt,
    ) {
        if ($this->endsAt->lessThan($this->startsAt)) {
            throw new \InvalidArgumentException('Reporting range: ends_at must be >= starts_at.');
        }
    }

    public static function fromDateStrings(string $from, string $to): self
    {
        $start = CarbonImmutable::parse($from)->startOfDay();
        $end = CarbonImmutable::parse($to)->endOfDay();

        return new self($start, $end);
    }

    /**
     * @return array{from: string, to: string}
     */
    public function toPeriodArray(): array
    {
        return [
            'from' => $this->startsAt->toDateString(),
            'to'   => $this->endsAt->toDateString(),
        ];
    }
}
