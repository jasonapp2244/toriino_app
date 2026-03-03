<?php

namespace App\Http\Controllers\Common;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notifications = AppNotification::where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->paginate(20);

        $unreadCount = AppNotification::where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->count();

        return ApiResponse::success([
            'unread_count'  => $unreadCount,
            'notifications' => $notifications,
        ]);
    }

    public function markAsRead(int $id, Request $request): JsonResponse
    {
        $notification = AppNotification::where('user_id', $request->user()->id)->find($id);

        if (!$notification) {
            return ApiResponse::notFound('Notification not found');
        }

        $notification->update(['read_at' => now()]);

        return ApiResponse::success(null, 'Notification marked as read');
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        AppNotification::where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return ApiResponse::success(null, 'All notifications marked as read');
    }

    public function destroy(int $id, Request $request): JsonResponse
    {
        $notification = AppNotification::where('user_id', $request->user()->id)->find($id);

        if (!$notification) {
            return ApiResponse::notFound('Notification not found');
        }

        $notification->delete();

        return ApiResponse::success(null, 'Notification deleted');
    }
}
