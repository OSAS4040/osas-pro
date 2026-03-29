<?php

/**
 * OSAS Intelligent layer — Phase 1 (foundation / events / observability).
 *
 * All flags default to false in production; enable explicitly via .env when ready.
 * Core financial flows MUST remain unchanged when flags are off.
 */
return [

    'events' => [
        /** Master switch: no domain event emission when false. */
        'enabled' => (bool) env('INTELLIGENT_EVENTS_ENABLED', false),

        'persist' => [
            /** Persist rows to `domain_events` when true (requires migration). */
            'enabled' => (bool) env('INTELLIGENT_EVENTS_PERSIST_ENABLED', false),
        ],
    ],

    'observability' => [
        /** Structured Log::info for domain events when true. */
        'enabled' => (bool) env('INTELLIGENT_OBSERVABILITY_ENABLED', false),
    ],

    'internal_dashboard' => [
        /** GET /api/v1/internal/domain-events and related inspection APIs. */
        'enabled' => (bool) env('INTELLIGENT_INTERNAL_DASHBOARD_ENABLED', false),
    ],

    /**
     * Phase 2 read-model surface (canonical flags). Back-compat: falls back to INTELLIGENT_PHASE2_*
     * or intelligent.phase2.* when set via config() in tests.
     */
    'read_models' => [
        'enabled' => (bool) env('INTELLIGENT_READ_MODELS_ENABLED', false)
            || (bool) env('INTELLIGENT_PHASE2_ENABLED', false),
    ],

    'insights' => [
        'enabled' => (bool) env('INTELLIGENT_INSIGHTS_ENABLED', false)
            || (bool) env('INTELLIGENT_PHASE2_INSIGHTS_ENABLED', false),
    ],

    'recommendations' => [
        'enabled' => (bool) env('INTELLIGENT_RECOMMENDATIONS_ENABLED', false)
            || (bool) env('INTELLIGENT_PHASE2_RECOMMENDATIONS_ENABLED', false),
    ],

    'alerts' => [
        'enabled' => (bool) env('INTELLIGENT_ALERTS_ENABLED', false)
            || (bool) env('INTELLIGENT_PHASE2_ALERTS_ENABLED', false),
    ],

    'overview_api' => [
        'enabled' => (bool) env('INTELLIGENT_OVERVIEW_API_ENABLED', false)
            || (bool) env('INTELLIGENT_PHASE2_OVERVIEW_ENABLED', false),
    ],

    /**
     * Phase 4 — Smart Command Center aggregate (GET only). Composes Phase 2 read models;
     * no writes, no automation.
     */
    'command_center_api' => [
        'enabled' => (bool) env('INTELLIGENT_COMMAND_CENTER_ENABLED', false),
    ],

    /**
     * Phase 7A — human-in-the-loop governance audit (append-only). No business execution.
     */
    'command_center_governance' => [
        'enabled' => (bool) env('INTELLIGENT_COMMAND_CENTER_GOVERNANCE_ENABLED', false),
    ],

    /**
     * When event persistence fails: store a row in `event_record_failures` (if migration exists).
     */
    'record_failures' => [
        'enabled' => (bool) env('INTELLIGENT_RECORD_FAILURES_ENABLED', true),
    ],

    /**
     * Phase 2 — read-only analytics over `domain_events` (+ diagnostic reads on
     * `event_record_failures`). No writes, no jobs, no operational table mutations.
     * Use read_models / insights / … env keys above, or legacy INTELLIGENT_PHASE2_*.
     */
    'phase2' => [
        'enabled' => (bool) env('INTELLIGENT_PHASE2_ENABLED', false)
            || (bool) env('INTELLIGENT_READ_MODELS_ENABLED', false),

        'features' => [
            'overview' => (bool) env('INTELLIGENT_PHASE2_OVERVIEW_ENABLED', false)
                || (bool) env('INTELLIGENT_OVERVIEW_API_ENABLED', false),
            'insights' => (bool) env('INTELLIGENT_PHASE2_INSIGHTS_ENABLED', false)
                || (bool) env('INTELLIGENT_INSIGHTS_ENABLED', false),
            'recommendations' => (bool) env('INTELLIGENT_PHASE2_RECOMMENDATIONS_ENABLED', false)
                || (bool) env('INTELLIGENT_RECOMMENDATIONS_ENABLED', false),
            'alerts' => (bool) env('INTELLIGENT_PHASE2_ALERTS_ENABLED', false)
                || (bool) env('INTELLIGENT_ALERTS_ENABLED', false),
            'command_center' => (bool) env('INTELLIGENT_COMMAND_CENTER_ENABLED', false),
        ],
    ],
];
