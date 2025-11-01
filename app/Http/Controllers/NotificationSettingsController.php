<?php

namespace App\Http\Controllers;

use App\Models\NotificationSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Handles user notification settings.
 */
class NotificationSettingsController extends Controller
{
  /**
   * Get the notification settings for the authenticated user.
   * Returns default settings if user doesn't have any settings.
   *
   * @return \Illuminate\Http\JsonResponse
   */
  public function getSettings()
  {
    $user = Auth::user();

    // Try to get existing settings
    $settings = NotificationSettings::where('user_id', $user->id)->first();

    // If no settings exist, return defaults
    if (!$settings) {
      $defaults = NotificationSettings::getDefaults();
      return response()->json([
        'notification_level' => $defaults['notify_type'],
        'email_contact' => $defaults['notify_email_contact'],
        'email_marketing' => $defaults['notify_email_marketing'],
        'email_social' => $defaults['notify_email_social'],
        'email_security' => $defaults['notify_email_security'],
      ]);
    }

    // Map notify_type to notification_level for frontend
    $notificationLevel = $settings->notify_type;
    if ($notificationLevel === 'direct_mentions') {
      $notificationLevel = 'mentions';
    }

    return response()->json([
      'notification_level' => $notificationLevel,
      'email_contact' => (bool) $settings->notify_email_contact,
      'email_marketing' => (bool) $settings->notify_email_marketing,
      'email_social' => (bool) $settings->notify_email_social,
      'email_security' => true, // Always true, cannot be changed
    ]);
  }

  /**
   * Update or create notification settings for the authenticated user.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function updateSettings(Request $request)
  {
    $user = Auth::user();

    // Validate incoming notification settings data
    // Note: email_security is not included as it cannot be changed (always true)
    $validatedData = $request->validate([
      'notification_level' => 'nullable|string|in:all,mentions,none',
      'email_contact' => 'nullable|boolean',
      'email_marketing' => 'nullable|boolean',
      'email_social' => 'nullable|boolean',
    ]);

    // Map notification_level to notify_type
    $notifyType = null;
    if (isset($validatedData['notification_level'])) {
      $notificationLevel = $validatedData['notification_level'];
      if ($notificationLevel === 'mentions') {
        $notifyType = 'direct_mentions';
      } else {
        $notifyType = $notificationLevel; // 'all' or 'none'
      }
      unset($validatedData['notification_level']);
    }

    // Prepare data for database
    // Note: email_security is always true and cannot be changed
    $settingsData = [];
    if ($notifyType !== null) {
      $settingsData['notify_type'] = $notifyType;
    }
    if (isset($validatedData['email_contact'])) {
      $settingsData['notify_email_contact'] = $validatedData['email_contact'];
    }
    if (isset($validatedData['email_marketing'])) {
      $settingsData['notify_email_marketing'] = $validatedData['email_marketing'];
    }
    if (isset($validatedData['email_social'])) {
      $settingsData['notify_email_social'] = $validatedData['email_social'];
    }
    // Always set email_security to true (cannot be changed)
    $settingsData['notify_email_security'] = true;

    // If no data to update, return success with current settings
    if (empty($settingsData)) {
      return $this->getSettings();
    }

    // Update or create settings
    $settings = NotificationSettings::updateOrCreate(
      ['user_id' => $user->id],
      array_merge($settingsData, ['updated_at' => now()])
    );

    // Map notify_type back to notification_level for response
    $notificationLevel = $settings->notify_type;
    if ($notificationLevel === 'direct_mentions') {
      $notificationLevel = 'mentions';
    }

    return response()->json([
      'message' => 'Cập nhật cài đặt thông báo thành công!',
      'notification_level' => $notificationLevel,
      'email_contact' => (bool) $settings->notify_email_contact,
      'email_marketing' => (bool) $settings->notify_email_marketing,
      'email_social' => (bool) $settings->notify_email_social,
      'email_security' => true, // Always true, cannot be changed
    ]);
  }
}

