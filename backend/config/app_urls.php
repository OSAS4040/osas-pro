<?php

/**
 * روابط مطلقة للواجهات العامة (QR، مشاركة، بريد).
 *
 * عند تشغيل Laravel خلف Docker قد يكون APP_URL=http://nginx — اضبط APP_PUBLIC_URL
 * على النطاق الذي يراه العميل (مثل https://app.example.com).
 */
return [
    'public_base' => env('APP_PUBLIC_URL', ''),
];
