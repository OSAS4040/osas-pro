<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class NotificationController extends Controller
{
    public function shareEmail(Request $request): JsonResponse
    {
        $request->validate([
            'to'          => ['required', 'email'],
            'subject'     => ['required', 'string', 'max:200'],
            'body'        => ['required', 'string', 'max:2000'],
            'url'         => ['required', 'url'],
            'entity_type' => ['sometimes', 'string'],
            'entity_id'   => ['sometimes', 'integer'],
        ]);

        try {
            Mail::raw(
                $request->body . "\n\n---\nتم الإرسال من نظام WorkshopOS",
                function ($msg) use ($request) {
                    $msg->to($request->to)
                        ->subject($request->subject)
                        ->from(
                            config('mail.from.address', 'noreply@workshopos.sa'),
                            config('mail.from.name',    'WorkshopOS')
                        );
                }
            );

            return response()->json([
                'message'  => 'Email sent successfully.',
                'trace_id' => app('trace_id'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message'  => 'Failed to send email: ' . $e->getMessage(),
                'trace_id' => app('trace_id'),
            ], 500);
        }
    }

    public function trackShare(Request $request): JsonResponse
    {
        // Silent tracking — log and return OK
        \Log::info('share_tracked', [
            'user_id'     => auth()->id(),
            'method'      => $request->input('method'),
            'entity_type' => $request->input('entity_type'),
            'entity_id'   => $request->input('entity_id'),
            'ip'          => $request->ip(),
        ]);

        return response()->json(['ok' => true, 'trace_id' => app('trace_id')]);
    }

    public function index(Request $request): JsonResponse
    {
        $notifications = $request->user()->notifications()->paginate(20);
        $unread = $request->user()->unreadNotifications()->count();
        return response()->json(['data' => $notifications, 'unread_count' => $unread]);
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        $request->user()->notifications()->where('id', $id)->update(['read_at' => now()]);
        return response()->json(['message' => 'تم التحديد كمقروء.']);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();
        return response()->json(['message' => 'تم تحديد الكل كمقروء.']);
    }
}
