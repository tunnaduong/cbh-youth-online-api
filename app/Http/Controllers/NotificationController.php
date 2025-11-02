<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\NotificationSubscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Handles notification-related API requests.
 */
class NotificationController extends Controller
{
  /**
   * Get all notifications for the authenticated user.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function index(Request $request)
  {
    $user = Auth::user();

    $query = Notification::where('user_id', $user->id)
      ->with(['actor.profile', 'notifiable'])
      ->orderBy('created_at', 'desc');

    // Filter by type if provided
    if ($request->has('type')) {
      $query->where('type', $request->type);
    }

    // Filter by read status if provided
    if ($request->has('read')) {
      if ($request->read === 'true' || $request->read === 1) {
        $query->read();
      } elseif ($request->read === 'false' || $request->read === 0) {
        $query->unread();
      }
    } else {
      // Default: show all
    }

    // Pagination
    $perPage = $request->get('per_page', 20);
    $perPage = min($perPage, 100); // Limit max per page
    $notifications = $query->paginate($perPage);

    $notifications->getCollection()->transform(function ($notification) {
      return $this->formatNotification($notification);
    });

    return response()->json([
      'notifications' => $notifications->items(),
      'pagination' => [
        'current_page' => $notifications->currentPage(),
        'last_page' => $notifications->lastPage(),
        'per_page' => $notifications->perPage(),
        'total' => $notifications->total(),
      ],
    ]);
  }

  /**
   * Get the count of unread notifications.
   *
   * @return \Illuminate\Http\JsonResponse
   */
  public function getUnreadCount()
  {
    $user = Auth::user();

    $count = Notification::where('user_id', $user->id)
      ->unread()
      ->count();

    return response()->json([
      'unread_count' => $count,
    ]);
  }

  /**
   * Get VAPID public key for push notifications.
   *
   * @return \Illuminate\Http\JsonResponse
   */
  public function getVapidPublicKey()
  {
    $publicKey = config('services.vapid.public_key');

    return response()->json([
      'public_key' => $publicKey,
    ]);
  }

  /**
   * Mark a specific notification as read.
   *
   * @param  int  $id
   * @return \Illuminate\Http\JsonResponse
   */
  public function markAsRead($id)
  {
    $user = Auth::user();

    $notification = Notification::where('id', $id)
      ->where('user_id', $user->id)
      ->firstOrFail();

    $notification->markAsRead();

    return response()->json([
      'message' => 'Notification marked as read',
      'notification' => $this->formatNotification($notification),
    ]);
  }

  /**
   * Mark all notifications as read for the authenticated user.
   *
   * @return \Illuminate\Http\JsonResponse
   */
  public function markAllAsRead()
  {
    $user = Auth::user();

    $updated = Notification::where('user_id', $user->id)
      ->whereNull('read_at')
      ->update(['read_at' => now()]);

    return response()->json([
      'message' => 'All notifications marked as read',
      'updated_count' => $updated,
    ]);
  }

  /**
   * Delete a specific notification.
   *
   * @param  int  $id
   * @return \Illuminate\Http\JsonResponse
   */
  public function destroy($id)
  {
    $user = Auth::user();

    $notification = Notification::where('id', $id)
      ->where('user_id', $user->id)
      ->firstOrFail();

    $notification->delete();

    return response()->json([
      'message' => 'Notification deleted',
    ]);
  }

  /**
   * Subscribe to push notifications.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function subscribe(Request $request)
  {
    Log::info('Push subscription request received', [
      'request_data' => $request->all(),
    ]);

    $request->validate([
      'endpoint' => 'required|string|url',
      'keys' => 'required|array',
      'keys.p256dh' => 'required|string',
      'keys.auth' => 'required|string',
      'expirationTime' => 'nullable|integer',
    ]);

    $user = Auth::user();

    if (!$user) {
      Log::warning('Push subscription request without authenticated user');
      return response()->json([
        'message' => 'Unauthorized',
      ], 401);
    }

    Log::info('Saving push subscription', [
      'user_id' => $user->id,
      'endpoint' => $request->endpoint,
    ]);

    try {
      $expiresAt = null;
      if ($request->has('expirationTime') && $request->expirationTime) {
        $expiresAt = now()->addSeconds($request->expirationTime);
      }

      // Check if subscription already exists for this user and endpoint
      // This prevents duplicates from race conditions
      $existingSubscription = NotificationSubscription::where('user_id', $user->id)
        ->where('endpoint', $request->endpoint)
        ->first();

      $isUpdate = $existingSubscription !== null;

      // Use updateOrCreate with user_id + endpoint as unique keys
      // This ensures only one subscription per user per endpoint
      $subscription = NotificationSubscription::updateOrCreate(
        [
          'user_id' => $user->id,
          'endpoint' => $request->endpoint,
        ],
        [
          'p256dh' => $request->keys['p256dh'],
          'auth' => $request->keys['auth'],
          'expires_at' => $expiresAt,
        ]
      );

      if ($isUpdate) {
        Log::info('Push subscription updated', [
          'subscription_id' => $subscription->id,
          'user_id' => $user->id,
          'endpoint' => $subscription->endpoint,
        ]);
      } else {
        Log::info('Push subscription created', [
          'subscription_id' => $subscription->id,
          'user_id' => $user->id,
          'endpoint' => $subscription->endpoint,
        ]);
      }

      return response()->json([
        'message' => 'Subscription saved successfully',
        'subscription' => [
          'id' => $subscription->id,
          'endpoint' => $subscription->endpoint,
          'expires_at' => $subscription->expires_at ? $subscription->expires_at->toISOString() : null,
        ],
      ]);
    } catch (\Exception $e) {
      Log::error('Error saving push subscription', [
        'user_id' => $user->id,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
      ]);

      return response()->json([
        'message' => 'Failed to save subscription',
        'error' => $e->getMessage(),
      ], 500);
    }
  }

  /**
   * Unsubscribe from push notifications.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function unsubscribe(Request $request)
  {
    $user = Auth::user();

    if (!$user) {
      Log::warning('Unsubscribe attempt without authentication');
      return response()->json([
        'message' => 'Unsubscribed successfully',
      ], 200); // Return success even if not authenticated
    }

    $endpoint = $request->input('endpoint');

    if ($endpoint) {
      // Unsubscribe specific endpoint
      $subscription = NotificationSubscription::where('user_id', $user->id)
        ->where('endpoint', $endpoint)
        ->first();

      if ($subscription) {
        $subscription->delete();
        Log::info('Push subscription deleted', [
          'subscription_id' => $subscription->id,
          'user_id' => $user->id,
          'endpoint' => $endpoint,
        ]);
      }
    } else {
      // Unsubscribe all subscriptions for user
      $deletedCount = NotificationSubscription::where('user_id', $user->id)->delete();
      Log::info('All push subscriptions deleted for user', [
        'user_id' => $user->id,
        'deleted_count' => $deletedCount,
      ]);
    }

    return response()->json([
      'message' => 'Unsubscribed successfully',
    ]);
  }

  /**
   * Get all push notification subscriptions for the authenticated user.
   *
   * @return \Illuminate\Http\JsonResponse
   */
  public function getSubscriptions()
  {
    $user = Auth::user();

    $subscriptions = NotificationSubscription::where('user_id', $user->id)
      ->get()
      ->map(function ($subscription) {
        return [
          'id' => $subscription->id,
          'endpoint' => $subscription->endpoint,
          'expires_at' => $subscription->expires_at ? $subscription->expires_at->toISOString() : null,
          'is_valid' => $subscription->isValid(),
        ];
      });

    return response()->json([
      'subscriptions' => $subscriptions,
    ]);
  }

  /**
   * Format a notification for API response.
   *
   * @param  \App\Models\Notification  $notification
   * @return array
   */
  private function formatNotification(Notification $notification): array
  {
    $actor = $notification->actor;

    return [
      'id' => $notification->id,
      'type' => $notification->type,
      'data' => $notification->data,
      'read_at' => $notification->read_at ? $notification->read_at->toISOString() : null,
      'created_at' => $notification->created_at->toISOString(),
      'created_at_human' => $notification->created_at->diffForHumans(),
      'is_read' => $notification->isRead(),
      'actor' => $actor ? [
        'id' => $actor->id,
        'username' => $actor->username,
        'profile_name' => $actor->profile->profile_name ?? $actor->username,
        'avatar_url' => config('app.url') . "/v1.0/users/{$actor->username}/avatar",
      ] : null,
    ];
  }
}
