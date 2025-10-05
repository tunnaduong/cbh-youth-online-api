<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Services\PointsService;

/**
 * Represents a vote on a topic.
 *
 * @property int $id
 * @property int $topic_id
 * @property int $user_id
 * @property int $vote_value Can be 1 for upvote or -1 for downvote.
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Topic $topic
 * @property-read \App\Models\AuthAccount $user
 */
class TopicVote extends Model
{
  use HasFactory;

  /**
   * The table associated with the model.
   *
   * @var string
   */
  protected $table = 'cyo_topic_votes';

  /**
   * The attributes that are mass assignable.
   *
   * @var array<int, string>
   */
  protected $fillable = [
    'topic_id',
    'user_id',
    'vote_value', // Assuming a value for upvote (1) and downvote (-1)
  ];

  /**
   * Get the topic that was voted on.
   *
   * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
   */
  public function topic()
  {
    return $this->belongsTo(Topic::class, 'topic_id');
  }

  /**
   * Get the user who voted.
   *
   * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
   */
  public function user()
  {
    return $this->belongsTo(AuthAccount::class, 'user_id');
  }

  /**
   * The "booted" method of the model.
   *
   * @return void
   */
  protected static function boot()
  {
    parent::boot();

    // Update points when a vote is created
    static::created(function ($vote) {
      PointsService::onVoteReceived($vote->topic->user_id);
    });

    // Update points when a vote is deleted
    static::deleted(function ($vote) {
      PointsService::onVoteReceived($vote->topic->user_id);
    });

    // Update points when a vote is updated
    static::updated(function ($vote) {
      PointsService::onVoteReceived($vote->topic->user_id);
    });
  }
}
