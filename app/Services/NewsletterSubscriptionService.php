<?php

namespace App\Services;

use App\Models\AuthAccount;
use Illuminate\Support\Facades\URL;

class NewsletterSubscriptionService
{
  public static function unsubscribeUrl(AuthAccount $account): string
  {
    return URL::signedRoute('newsletter.unsubscribe', [
      'account' => $account->id,
    ]);
  }

  public static function unsubscribeUrlForEmail(?string $email): ?string
  {
    if (!$email) {
      return null;
    }

    $account = AuthAccount::where('email', $email)->first();

    return $account ? self::unsubscribeUrl($account) : null;
  }
}
