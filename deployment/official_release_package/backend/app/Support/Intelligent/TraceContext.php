<?php

namespace App\Support\Intelligent;

use Illuminate\Support\Str;

/**
 * Trace/correlation helpers for the intelligent layer (HTTP + jobs).
 *
 * Core boundaries:
 * - HTTP: TraceRequestMiddleware sets trace_id + correlation_id on the container.
 * - Console/queue: may be unset; domain events store nullable trace_id.
 *
 * @see \App\Http\Middleware\TraceRequestMiddleware
 */
final class TraceContext
{
    public static function traceId(): ?string
    {
        if (app()->bound('trace_id')) {
            return (string) app('trace_id');
        }

        return null;
    }

    public static function correlationId(): ?string
    {
        if (app()->bound('correlation_id')) {
            return (string) app('correlation_id');
        }

        return null;
    }

    /**
     * @return array{trace_id: ?string, correlation_id: ?string}
     */
    public static function contextArray(): array
    {
        return [
            'trace_id'        => self::traceId(),
            'correlation_id'  => self::correlationId(),
        ];
    }

    /**
     * Resolve trace id for persistence (never throws).
     */
    public static function traceIdForEvent(): ?string
    {
        $t = self::traceId();

        return $t !== '' ? $t : null;
    }

    public static function freshCorrelationId(): string
    {
        return (string) Str::uuid();
    }
}
