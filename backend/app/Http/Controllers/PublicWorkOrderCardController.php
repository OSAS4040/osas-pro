<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\WorkOrder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;

final class PublicWorkOrderCardController extends Controller
{
    /**
     * صفحة خفيفة للتحقق من أمر العمل عند مسح رمز QR (بدون مصادقة).
     */
    public function show(string $uuid): View|Response
    {
        $order = WorkOrder::query()
            ->where('uuid', $uuid)
            ->with(['customer', 'vehicle', 'company', 'branch'])
            ->first();

        if (! $order) {
            return response()->view('public.work-order-card-missing', [], 404);
        }

        return view('public.work-order-card', ['order' => $order]);
    }
}
