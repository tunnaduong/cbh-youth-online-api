<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Audit record of an admin reading users' private messages.
 */
class AdminMessageAccessLog extends Model
{
  protected $table = 'cyo_admin_message_access_logs';

  protected $fillable = ['admin_id', 'conversation_id', 'action', 'query', 'ip'];

  public function admin()
  {
    return $this->belongsTo(AuthAccount::class, 'admin_id');
  }

  public function conversation()
  {
    return $this->belongsTo(Conversation::class, 'conversation_id');
  }
}
