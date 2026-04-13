<?php

/**
 * بيانات الإصدار/النشر — تُضبط من CI أو Docker build لتطابق الواجهة (dist).
 *
 * @see GET /api/v1/system/version
 */
return [
    'version' => env('APP_RELEASE_VERSION', '1.0.1'),
    'commit' => env('APP_RELEASE_COMMIT', 'unknown'),
    'branch' => env('APP_RELEASE_BRANCH', 'unknown'),
    'build_time' => env('APP_RELEASE_BUILD_TIME'),
    'environment' => env('APP_RELEASE_ENV', env('APP_ENV', 'local')),
];
