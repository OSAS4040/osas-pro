<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web routes (marketing / SPA fallbacks)
|--------------------------------------------------------------------------
|
| When the browser hits the Laravel host directly (e.g. php artisan serve
| on :8000), deep links like /landing are not served by Vite. Redirect
| to the public frontend URL so the Vue app can handle the route.
|
| Set FRONTEND_PUBLIC_URL in .env (e.g. http://localhost:5173 locally, or
| http://localhost when nginx proxies the SPA on port 80).
|
*/

Route::get('/landing', function () {
    $base = rtrim((string) env('FRONTEND_PUBLIC_URL', 'http://localhost:5173'), '/');

    return redirect()->away($base.'/landing');
});

/** بطاقة عامة لأمر العمل (QR) — بيانات محدودة دون تسجيل دخول */
Route::get('/public/work-orders/{uuid}', [\App\Http\Controllers\PublicWorkOrderCardController::class, 'show'])
    ->name('work-order.public.card');
