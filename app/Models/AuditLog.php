<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * One entry of the audit log (cyo_audit_logs): a change someone made, with
 * the data before and after it and where it stands.
 *
 * Entries are written through record() / recordChanges() and are never
 * edited afterwards, except for `status` (the admin screen marks a pending
 * entry as handled).
 */
class AuditLog extends Model
{
  public const STATUS_PENDING = 'pending';
  public const STATUS_UPDATED = 'updated';
  public const STATUS_RESOLVED = 'resolved';

  public const STATUSES = [self::STATUS_PENDING, self::STATUS_UPDATED, self::STATUS_RESOLVED];

  // Values that must never be copied into the log.
  private const SECRET_FIELDS = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes', 'provider_token'];

  protected $table = 'cyo_audit_logs';

  protected $fillable = ['user_id', 'actor_id', 'action_type', 'target_type', 'target_id', 'old_data', 'new_data', 'status'];

  protected $casts = [
    'old_data' => 'array',
    'new_data' => 'array',
  ];

  public function user()
  {
    return $this->belongsTo(AuthAccount::class, 'user_id');
  }

  public function actor()
  {
    return $this->belongsTo(AuthAccount::class, 'actor_id');
  }

  /**
   * Write one entry. Never throws: a failure to log (for instance before
   * the table has been migrated) must not fail the action being logged.
   *
   * @param  string  $actionType  UPDATE_PROFILE, EDIT_POST, ADMIN_RESET_PASSWORD...
   * @param  int|null  $userId  the user the change concerns
   * @param  array  $options  old, new, target_type, target_id, status, actor_id
   */
  public static function record(string $actionType, ?int $userId, array $options = []): ?self
  {
    try {
      $actorId = array_key_exists('actor_id', $options) ? $options['actor_id'] : Auth::id();

      return self::create([
        'user_id' => $userId,
        // Only stored when someone else acted on the user's data.
        'actor_id' => $actorId && (int) $actorId !== (int) $userId ? $actorId : null,
        'action_type' => $actionType,
        'target_type' => $options['target_type'] ?? null,
        'target_id' => $options['target_id'] ?? null,
        'old_data' => self::clean($options['old'] ?? null),
        'new_data' => self::clean($options['new'] ?? null),
        'status' => $options['status'] ?? self::STATUS_UPDATED,
      ]);
    } catch (\Throwable $e) {
      Log::warning('Could not write the audit log', ['action' => $actionType, 'error' => $e->getMessage()]);

      return null;
    }
  }

  /**
   * Log what a model's save changed, limited to $fields (so counters and
   * timestamps don't flood the log). Call from the model's "updated" event.
   * Nothing is written when none of $fields changed.
   */
  public static function recordChanges(string $actionType, Model $model, array $fields, ?int $userId, string $targetType): void
  {
    try {
      $changed = array_intersect_key($model->getChanges(), array_flip($fields));
      if (empty($changed)) {
        return;
      }

      $old = [];
      foreach (array_keys($changed) as $field) {
        $old[$field] = $model->getOriginal($field);
      }

      self::record($actionType, $userId, [
        'old' => $old,
        'new' => $changed,
        'target_type' => $targetType,
        'target_id' => $model->getKey(),
      ]);
    } catch (\Throwable $e) {
      Log::warning('Could not write the audit log', ['action' => $actionType, 'error' => $e->getMessage()]);
    }
  }

  private static function clean(?array $data): ?array
  {
    if ($data === null) {
      return null;
    }

    foreach (self::SECRET_FIELDS as $field) {
      unset($data[$field]);
    }

    return $data;
  }
}
