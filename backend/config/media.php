<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Tenant-facing uploads (logos, invoices before/after, vehicle docs, …)
    |--------------------------------------------------------------------------
    |
    | استخدم "public" محلياً؛ في الإنتاج واسع النطاق غيّر إلى "s3" (أو توافق S3)
    | بعد ضبط متغيرات AWS_* أو WASABI_* حسب القرص في filesystems.php.
    |
    */

    'tenant_upload_disk' => env('MEDIA_TENANT_UPLOAD_DISK', 'public'),

];
