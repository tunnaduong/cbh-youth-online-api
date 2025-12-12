<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Represents a user-submitted report against another user or content.
 *
 * @property int $id
 * @property int $user_id The ID of the user who submitted the report.
 * @property int $reported_user_id The ID of the user being reported.
 * @property int|null $topic_id The ID of the topic related to the report.
 * @property string|null $reason
 * @property string $status
 * @property string|null $admin_notes
 * @property int|null $reviewed_by The ID of the admin who reviewed the report.
 * @property \Illuminate\Support\Carbon|null $reviewed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\AuthAccount $reporter
 * @property-read \App\Models\AuthAccount $reportedUser
 * @property-read \App\Models\Topic|null $topic
 * @property-read \App\Models\AuthAccount|null $reviewedBy
 */
class UserReport extends Model
{
  use HasFactory;

  /**
   * The table associated with the model.
   *
   * @var string
   */
  protected $table = 'cyo_user_reports';

  /**
   * The attributes that are mass assignable.
   *
   * @var array<int, string>
   */
  protected $fillable = [
    'user_id',
    'reported_user_id',
    'topic_id',
    'story_id',
    'reason',
    'status',  // pending, reviewed, resolved, dismissed
    'admin_notes',
    'reviewed_by',
    'reviewed_at'
  ];

  /**
   * The attributes that should be cast.
   *
   * @var array<string, string>
   */
  protected $casts = [
    'reviewed_at' => 'datetime'
  ];

  /**
   * Get the user who submitted the report.
   *
   * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
   */
  public function reporter()
  {
    return $this->belongsTo(AuthAccount::class, 'user_id');
  }

  /**
   * Get the user who was reported.
   *
   * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
   */
  public function reportedUser()
  {
    return $this->belongsTo(AuthAccount::class, 'reported_user_id');
  }

  /**
   * Get the topic associated with the report.
   *
   * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
   */
  public function topic()
  {
    return $this->belongsTo(Topic::class, 'topic_id');
  }

  /**
   * Get the story associated with the report.
   *
   * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
   */
  public function story()
  {
    return $this->belongsTo(Story::class, 'story_id');
  }

  /**
   * Get the admin who reviewed the report.
   *
   * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
   */
  public function reviewedBy()
  {
    return $this->belongsTo(AuthAccount::class, 'reviewed_by');
  }
}
