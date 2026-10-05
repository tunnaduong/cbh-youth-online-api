<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One entry of the audit log (cyo_audit_logs): a change someone made, with
 * the data before and after it and where it stands.
 */
class AuditLog extends Model
{
  public const STATUS_PENDING = 'pending';
  public const STATUS_UPDATED = 'updated';
  public const STATUS_RESOLVED = 'resolved';

  protected $table = 'cyo_audit_logs';

  protected $fillable = ['user_id', 'action_type', 'old_data', 'new_data', 'status'];

  protected $casts = [
    'old_data' => 'array',
    'new_data' => 'array',
  ];

  public function user()
  {
    return $this->belongsTo(AuthAccount::class, 'user_id');
  }
}
