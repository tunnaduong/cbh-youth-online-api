<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Represents a topic a user chose to hide from their own feed.
 *
 * This is per-user and purely a feed filter: the topic itself stays public and
 * is still reachable by direct link, unlike the moderation-level `hidden`
 * column on the topic itself.
 *
 * @property int $id
 * @property int $user_id
 * @property int $topic_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\AuthAccount $user
 * @property-read \App\Models\Topic $topic
 */
class UserHiddenTopic extends Model
{
  /**
   * The table associated with the model.
   *
   * @var string
   */
  protected $table = 'cyo_user_hidden_topics';

  /**
   * The attributes that are mass assignable.
   *
   * @var array<int, string>
   */
  protected $fillable = [
    'user_id',
    'topic_id',
  ];

  /**
   * Get the user who hid the topic.
   *
   * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
   */
  public function user()
  {
    return $this->belongsTo(AuthAccount::class);
  }

  /**
   * Get the topic that was hidden.
   *
   * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
   */
  public function topic()
  {
    return $this->belongsTo(Topic::class);
  }
}
