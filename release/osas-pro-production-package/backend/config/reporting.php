<?php

/**
 * WAVE 2 / PR7 — read-only reporting foundation (limits & export placeholders only).
 */
return [
    /** Maximum inclusive calendar span for `from` / `to` filters. */
    'max_date_range_days' => max(1, min(366, (int) env('REPORTING_MAX_DATE_RANGE_DAYS', 120))),

    /** Hard cap on detail rows when list-style reports are added later (aggregates only in PR7). */
    'max_detail_rows' => max(50, min(5000, (int) env('REPORTING_MAX_DETAIL_ROWS', 500))),

    'export' => [
        /** Enable WAVE 2 / PR12 export routes (`REPORTING_EXPORT_ENABLED`). Off by default. */
        'enabled' => filter_var(env('REPORTING_EXPORT_ENABLED', false), FILTER_VALIDATE_BOOL),
        /** Lowercase list: csv, xlsx, pdf (`REPORTING_EXPORT_FORMATS`). */
        'formats_supported' => array_values(array_filter(array_map(
            static fn (string $f): string => strtolower(trim($f)),
            explode(',', (string) env('REPORTING_EXPORT_FORMATS', 'csv,xlsx,pdf'))
        ))),
        /** Max rows fetched for list exports (e.g. global operations feed snapshot). */
        'max_rows' => max(50, min(5000, (int) env('REPORTING_EXPORT_MAX_ROWS', 500))),
        /** Hard cap on PDF table rows (DomPDF memory). */
        'pdf_max_rows' => max(50, min(2000, (int) env('REPORTING_EXPORT_PDF_MAX_ROWS', 400))),
    ],

    /** Max weekly buckets returned for platform time-series breakdowns. */
    'platform_max_time_buckets' => max(4, min(64, (int) env('REPORTING_PLATFORM_MAX_TIME_BUCKETS', 32))),

    /** WAVE 2 / PR11 — global operations feed pagination (hard cap). */
    'global_feed_default_per_page' => max(5, min(100, (int) env('REPORTING_GLOBAL_FEED_DEFAULT_PER_PAGE', 25))),
    'global_feed_max_per_page' => max(10, min(200, (int) env('REPORTING_GLOBAL_FEED_MAX_PER_PAGE', 100))),
];
