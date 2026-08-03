<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\PushSubscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class NotificationController extends Controller
{
    /**
     * Display a list of notifications for the logged-in user.
     * GET: /notifications
     */
    public function index(Request $request)
    {
        $userId = Auth::id();

        $notifications = Notification::where('user_id', $userId)->where('is_read', 0)
            ->orderBy('created_at', 'DESC')
            ->paginate(10);

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'data'    => $notifications,
            ]);
        }

        return view('notifications.index', [
            'notifications' => $notifications,
        ]);
    }

    /**
     * Poll new notifications for the logged-in user (web push-like toast).
     * GET: /notifications/poll?last_id=123
     */
    public function poll(Request $request)
    {
        $userId = Auth::id();

        if (!$userId) {
            return response()->json([
                'status'  => false,
                'message' => 'User not authenticated.',
                'data'    => [],
            ], 401);
        }

        $lastId = (int) $request->get('last_id', 0);

        $unreadCount = Notification::where('user_id', $userId)
            ->where('is_read', 0)
            ->count();

        // First poll after page load: sync cursor to newest ID and seed dropdown
        // with recent unread items (do not walk from id=1 through old history).
        if ($lastId <= 0) {
            $maxId = (int) (Notification::where('user_id', $userId)->max('id') ?? 0);
            $seed = Notification::where('user_id', $userId)
                ->where('is_read', 0)
                ->orderByDesc('id')
                ->limit(3)
                ->get()
                ->sortBy('id')
                ->values();

            return response()->json([
                'status' => true,
                'data'   => [
                    'unreadCount'   => $unreadCount,
                    'notifications' => $seed,
                    'last_id'       => $maxId,
                    'synced'        => true,
                ],
            ]);
        }

        $newNotifications = Notification::where('user_id', $userId)
            ->where('id', '>', $lastId)
            ->orderBy('id', 'ASC')
            ->limit(20)
            ->get();

        return response()->json([
            'status' => true,
            'data'   => [
                'unreadCount'   => $unreadCount,
                'notifications' => $newNotifications,
                'last_id'       => $newNotifications->isNotEmpty() ? $newNotifications->last()->id : $lastId,
                'synced'        => false,
            ],
        ]);
    }

    /**
     * Mark all notifications as read for the logged-in user.
     * PATCH: /notifications/mark-all-read
     */
    public function markAllAsRead()
    {
        $updated = Notification::where('user_id', Auth::id())
            ->where('is_read', 0)
            ->update(['is_read' => 1]);

        return response()->json([
            'success' => true,
            'message' => "All notifications marked as read ({$updated} updated).",
        ]);
    }

    /**
     * Delete all notifications for the logged-in user.
     * DELETE: /notifications/delete-all
     */
    public function deleteAll()
    {
        $deleted = Notification::where('user_id', Auth::id())->delete();

        return response()->json([
            'success' => true,
            'message' => "All notifications deleted ({$deleted} removed).",
        ]);
    }

    /**
     * Delete a notification.
     * DELETE: /notifications/{id}
     */
    public function deleteNotification($id)
    {
        $notification = Notification::find($id);

        if (!$notification) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found.'
            ], 404);
        }

        if ($notification->user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.'
            ], 403);
        }

        if ($notification->delete()) {
            return response()->json([
                'success' => true,
                'message' => 'Notification deleted successfully.'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Unable to delete notification.'
        ], 500);
    }

    /**
     * Mark a notification as read.
     * PATCH: /notifications/{id}/read
     */
    public function markAsRead($id)
    {
        $notification = Notification::find($id);

        if (!$notification) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found.'
            ], 404);
        }

        if ($notification->user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.'
            ], 403);
        }

        $notification->is_read = 1;

        if ($notification->save()) {
            return response()->json([
                'success' => true,
                'message' => 'Notification marked as read.'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Unable to update notification.'
        ], 500);
    }

    /**
     * Save push subscription for the logged-in user.
     * POST: /notifications/subscribe
     */
    // public function subscribe(Request $request)
    // {
    //     $userId = Auth::id();

    //     if (!$userId) {
    //         Log::warning('Push subscription attempt without authentication');
    //         return response()->json([
    //             'status'  => false,
    //             'message' => 'User not authenticated.',
    //         ], 401);
    //     }

    //     $endpoint = $request->input('endpoint');
    //     $p256dh = $request->input('keys.p256dh', '');
    //     $auth = $request->input('keys.auth', '');

    //     if (!$endpoint) {
    //         Log::warning('Invalid subscription data received for user ID: ' . $userId);
    //         return response()->json([
    //             'status'  => false,
    //             'message' => 'Invalid subscription data.',
    //         ], 400);
    //     }

    //     Log::info("Processing push subscription for user ID: {$userId}, endpoint: " . substr($endpoint, 0, 50) . '...');

    //     try {
    //         // Ensure an endpoint belongs to only ONE user at a time
    //         $removed = PushSubscription::where('endpoint', $endpoint)
    //             ->where('user_id', '!=', $userId)
    //             ->delete();

    //         if ($removed) {
    //             Log::info("Removed {$removed} push subscription(s) for other users with same endpoint. Endpoint re-assigned to user {$userId}");
    //         }
    //     } catch (\Exception $e) {
    //         Log::error('Error cleaning duplicate endpoint subscriptions: ' . $e->getMessage());
    //     }

    //     // Check if subscription already exists
    //     $subscription = PushSubscription::updateOrCreate(
    //         ['user_id' => $userId, 'endpoint' => $endpoint],
    //         ['p256dh' => $p256dh, 'auth' => $auth]
    //     );

    //     Log::info("Push subscription processed for user ID: {$userId}, subscription ID: {$subscription->id}");

    //     $totalSubscriptions = PushSubscription::where('user_id', $userId)->count();
    //     Log::info("User ID {$userId} now has {$totalSubscriptions} push subscription(s)");

    //     return response()->json([
    //         'status' => true,
    //         'message' => 'Subscription saved successfully.',
    //     ]);
    // }

    /**
     * Get VAPID public key for client-side subscription.
     * GET: /notifications/vapid-key
     */
    // public function vapidKey()
    // {
    //     $vapidPublicKey = env('VAPID_PUBLIC_KEY', '');
        
    //     if (empty($vapidPublicKey)) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'VAPID public key not configured.',
    //         ], 500);
    //     }

    //     return response()->json([
    //         'status' => true,
    //         'publicKey' => $vapidPublicKey,
    //     ]);
    // }
}
