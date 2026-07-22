<?php

namespace App\Http\Controllers;

use App\Models\AuthAccount;
use App\Models\NotificationSettings;
use Illuminate\Http\Request;

class NewsletterSubscriptionController extends Controller
{
  public function unsubscribe(Request $request, AuthAccount $account)
  {
    abort_unless($request->hasValidSignature(), 403);

    NotificationSettings::updateOrCreate(
      ['user_id' => $account->id],
      [
        'notify_email_marketing' => false,
        'notify_email_social' => false,
      ]
    );

    $frontendUrl = rtrim(env('APP_UI_URL', 'http://localhost:3000'), '/');

    return redirect()->away($frontendUrl . '/unsubscribe?status=success');
  }
}
