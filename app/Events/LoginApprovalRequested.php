<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Broadcast on the owner's private channel when a login is waiting to be
 * approved on a logged-in device (two-factor method "device", see
 * LoginApprovalService). A client that is open shows the request right away;
 * it then reads the details from GET /two-factor/approvals. Only the id is
 * sent: the numbers to pick from stay behind the authenticated endpoint.
 */
class LoginApprovalRequested implements ShouldBroadcastNow
{
  use Dispatchable;
  use InteractsWithSockets;

  public function __construct(public int $userId, public string $approvalId)
  {
  }

  public function broadcastOn()
  {
    return new PrivateChannel('App.Models.User.' . $this->userId);
  }

  public function broadcastAs()
  {
    return 'login.approval';
  }

  public function broadcastWith()
  {
    return ['approval_id' => $this->approvalId];
  }
}
