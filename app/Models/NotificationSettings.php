<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Represents a user's notification settings.
 *
 * @property int $id
 * @property int $user_id
 * @property string|null $notify_type
 * @property bool|null $notify_email_contact
 * @property bool|null $notify_email_marketing
 * @property bool|null $notify_email_social
 * @property bool|null $notify_email_security
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\AuthAccount $user
 */
class NotificationSettings extends Model
{
  use HasFactory;

  /**
   * The table associated with the model.
   *
   * @var string
   */
  protected $table = 'cyo_notification_settings';

  /**
   * Indicates if the model should be timestamped.
   *
   * @var bool
   */
  public $timestamps = true;

  /**
   * The name of the "updated at" column.
   *
   * @var string
   */
  const UPDATED_AT = 'updated_at';

  /**
   * The name of the "created at" column.
   *
   * @var string
   */
  const CREATED_AT = null;

  /**
   * The attributes that should be cast.
   *
   * @var array<string, string>
   */
  protected $casts = [
    'notify_email_contact' => 'boolean',
    'notify_email_marketing' => 'boolean',
    'notify_email_social' => 'boolean',
    'notify_email_security' => 'boolean',
  ];

  /**
   * The attributes that are mass assignable.
   *
   * @var array<int, string>
   */
  protected $fillable = [
    'user_id',
    'notify_type',
    'notify_email_contact',
    'notify_email_marketing',
    'notify_email_social',
    'notify_email_security',
  ];

  /**
   * Get the user that owns the notification settings.
   *
   * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
   */
  public function user()
  {
    return $this->belongsTo(AuthAccount::class, 'user_id');
  }

  /**
   * Get default notification settings.
   * All settings are enabled by default.
   *
   * @return array
   */
  public static function getDefaults()
  {
    return [
      'notify_type' => 'all',
      'notify_email_contact' => true,
      'notify_email_marketing' => true,
      'notify_email_social' => true,
      'notify_email_security' => true,
    ];
  }

}

