<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A bug report or suggestion sent from the in-app feedback form (web, iOS,
 * Android). Replaces the old Google Form.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $type bug | suggestion | other
 * @property string $content
 * @property array|null $image_urls
 * @property string|null $contact_email
 * @property string $platform web | ios | android
 * @property string|null $app_version
 * @property string|null $device_info
 * @property string|null $page_url
 * @property string $status new | in_progress | resolved | closed
 * @property string|null $admin_notes
 * @property int|null $reviewed_by
 * @property \Illuminate\Support\Carbon|null $reviewed_at
 */
class Feedback extends Model
{
  protected $table = 'cyo_feedback';

  public const TYPES = ['bug', 'suggestion', 'other'];
  public const PLATFORMS = ['web', 'ios', 'android'];
  public const STATUSES = ['new', 'in_progress', 'resolved', 'closed'];

  protected $fillable = [
    'user_id',
    'type',
    'content',
    'image_urls',
    'contact_email',
    'platform',
    'app_version',
    'device_info',
    'page_url',
    'status',
    'admin_notes',
    'reviewed_by',
    'reviewed_at',
    'ip_address',
  ];

  protected $hidden = ['ip_address'];

  protected $casts = [
    'image_urls' => 'array',
    'reviewed_at' => 'datetime',
  ];

  public function user()
  {
    return $this->belongsTo(AuthAccount::class, 'user_id');
  }

  public function reviewer()
  {
    return $this->belongsTo(AuthAccount::class, 'reviewed_by');
  }
}
