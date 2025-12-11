<?php

namespace App\Http\Controllers;

use App\Models\ExpoPushToken;
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
    $perPage = min($perPage, 100);  // Limit max per page
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

      // Cleanup old subscriptions for this user (keep only the latest 3 subscriptions)
      // This prevents database bloat when browser creates new endpoints on each refresh
      $userSubscriptions = NotificationSubscription::where('user_id', $user->id)
        ->orderBy('created_at', 'desc')
        ->get();

      // If user has more than 3 subscriptions, delete the oldest ones
      if ($userSubscriptions->count() > 3) {
        $subscriptionsToDelete = $userSubscriptions->slice(3);
        $deletedCount = 0;
        foreach ($subscriptionsToDelete as $oldSubscription) {
          // Don't delete if it's the same endpoint we're about to update/create
          if ($oldSubscription->endpoint !== $request->endpoint) {
            $oldSubscription->delete();
            $deletedCount++;
          }
        }
        if ($deletedCount > 0) {
          Log::info('Cleaned up old push subscriptions', [
            'user_id' => $user->id,
            'deleted_count' => $deletedCount,
          ]);
        }
      }

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
      ], 200);  // Return success even if not authenticated
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
   * Register an Expo push token.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function registerExpoPushToken(Request $request)
  {
    Log::info('Expo push token registration request received', [
      'request_data' => $request->all(),
    ]);

    $request->validate([
      'expo_push_token' => 'required|string',
      'device_type' => 'nullable|string|in:ios,android',
      'device_id' => 'nullable|string|max:255',
    ]);

    $user = Auth::user();

    if (!$user) {
      Log::warning('Expo push token registration request without authenticated user');
      return response()->json([
        'message' => 'Unauthorized',
      ], 401);
    }

    Log::info('Saving Expo push token', [
      'user_id' => $user->id,
      'expo_push_token' => $request->expo_push_token,
    ]);

    try {
      // Update or create based on the unique token
      // If the token exists (even for another user), claim it for the current user
      // This handles the case where multiple users log in on the same device
      $token = ExpoPushToken::updateOrCreate(
        [
          'expo_push_token' => $request->expo_push_token,
        ],
        [
          'user_id' => $user->id,
          'device_type' => $request->device_type,
          'device_id' => $request->device_id,
          'is_active' => true,
          'last_used_at' => now(),
        ]
      );

      Log::info('Expo push token saved', [
        'token_id' => $token->id,
        'user_id' => $user->id,
        'expo_push_token' => $token->expo_push_token,
      ]);

      return response()->json([
        'message' => 'Expo push token registered successfully',
        'token' => [
          'id' => $token->id,
          'expo_push_token' => $token->expo_push_token,
          'device_type' => $token->device_type,
          'is_active' => $token->is_active,
        ],
      ]);
    } catch (\Exception $e) {
      Log::error('Error saving Expo push token', [
        'user_id' => $user->id,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
      ]);

      return response()->json([
        'message' => 'Failed to register Expo push token',
        'error' => $e->getMessage(),
      ], 500);
    }
  }

  /**
   * Unregister an Expo push token.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function unregisterExpoPushToken(Request $request)
  {
    $user = Auth::user();

    if (!$user) {
      Log::warning('Expo push token unregistration attempt without authentication');
      return response()->json([
        'message' => 'Unregistered successfully',
      ], 200);  // Return success even if not authenticated
    }

    $expoPushToken = $request->input('expo_push_token');

    if ($expoPushToken) {
      // Unregister specific token
      $token = ExpoPushToken::where('user_id', $user->id)
        ->where('expo_push_token', $expoPushToken)
        ->first();

      if ($token) {
        $token->deactivate();
        Log::info('Expo push token deactivated', [
          'token_id' => $token->id,
          'user_id' => $user->id,
          'expo_push_token' => $expoPushToken,
        ]);
      }
    } else {
      // Deactivate all tokens for user
      $deactivatedCount = ExpoPushToken::where('user_id', $user->id)
        ->where('is_active', true)
        ->update(['is_active' => false]);
      Log::info('All Expo push tokens deactivated for user', [
        'user_id' => $user->id,
        'deactivated_count' => $deactivatedCount,
      ]);
    }

    return response()->json([
      'message' => 'Expo push token unregistered successfully',
    ]);
  }

  /**
   * Get all Expo push tokens for the authenticated user.
   *
   * @return \Illuminate\Http\JsonResponse
   */
  public function getExpoPushTokens()
  {
    $user = Auth::user();

    $tokens = ExpoPushToken::where('user_id', $user->id)
      ->get()
      ->map(function ($token) {
        return [
          'id' => $token->id,
          'expo_push_token' => $token->expo_push_token,
          'device_type' => $token->device_type,
          'device_id' => $token->device_id,
          'is_active' => $token->is_active,
          'last_used_at' => $token->last_used_at ? $token->last_used_at->toISOString() : null,
          'created_at' => $token->created_at->toISOString(),
        ];
      });

    return response()->json([
      'tokens' => $tokens,
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
