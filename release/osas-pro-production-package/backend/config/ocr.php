<?php

return [

    /*
    |--------------------------------------------------------------------------
    | OCR master switch
    |--------------------------------------------------------------------------
    |
    | When false, OCR endpoints return "engine unavailable" style responses
    | without executing Tesseract (useful for locked-down environments).
    |
    */
    'enabled' => filter_var(env('OCR_ENABLED', true), FILTER_VALIDATE_BOOLEAN),

    /*
    |--------------------------------------------------------------------------
    | Tesseract executable
    |--------------------------------------------------------------------------
    |
    | Leave empty to auto-detect: config path first, then known Linux paths,
    | then command -v / where. In Docker (Alpine/Debian) typical path is
    | /usr/bin/tesseract.
    |
    */
    'tesseract_path' => env('OCR_TESSERACT_PATH', ''),

    /*
    |--------------------------------------------------------------------------
    | Languages (Tesseract -l)
    |--------------------------------------------------------------------------
    */
    'default_lang_plate' => env('OCR_DEFAULT_LANG_PLATE', 'eng+ara'),

    'default_lang_document' => env('OCR_DEFAULT_LANG_DOCUMENT', 'ara+eng'),

    /*
    |--------------------------------------------------------------------------
    | Runtime limits
    |--------------------------------------------------------------------------
    */
    'timeout_seconds' => (int) env('OCR_TIMEOUT_SECONDS', 90),

    'max_image_bytes' => (int) env('OCR_MAX_IMAGE_BYTES', 12582912),

    /*
    |--------------------------------------------------------------------------
    | Health check (php artisan ocr:verify)
    |--------------------------------------------------------------------------
    |
    | Comma-separated list in .env: OCR_REQUIRED_LANGS=ara,eng
    |
    */
    'required_langs' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('OCR_REQUIRED_LANGS', 'ara,eng'))
    ))),

];
