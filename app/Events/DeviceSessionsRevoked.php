<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Broadcast when logins are revoked from the "logged-in devices" list, on the
 * owner's private channel. The token ids let a client that is still open on
 * one of those logins (the mobile app keeps a socket connected) sign itself
 * out right away instead of on its next failed request. A Sanctum token's id
 * is the number before the "|" in its plain-text form, so a client can tell
 * whether it's affected without asking the API.
 */
class DeviceSessionsRevoked implements ShouldBroadcastNow
{
  use Dispatchable;
  use InteractsWithSockets;

  /**
   * @param  int  $userId
   * @param  int[]  $tokenIds
   */
  public function __construct(public int $userId, public array $tokenIds)
  {
  }

  public function broadcastOn()
  {
    return new PrivateChannel('App.Models.User.' . $this->userId);
  }

  public function broadcastAs()
  {
    return 'session.revoked';
  }

  public function broadcastWith()
  {
    return ['token_ids' => array_values(array_map('intval', $this->tokenIds))];
  }
}
