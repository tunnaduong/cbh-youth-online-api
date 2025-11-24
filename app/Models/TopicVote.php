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

    // Add points when a vote is created (+5 points)
    static::created(function ($vote) {
      if ($vote->vote_value == 1) { // Only upvotes give points
        PointsService::onVoteReceived($vote->topic->user_id);
      }
    });

    // Deduct points when a vote is deleted (-5 points)
    static::deleted(function ($vote) {
      if ($vote->vote_value == 1) { // Only upvotes give points
        PointsService::onVoteRemoved($vote->topic->user_id);
      }
    });

    // Handle vote changes (e.g., upvote to downvote)
    static::updated(function ($vote) {
      $originalVoteValue = $vote->getOriginal('vote_value');
      $newVoteValue = $vote->vote_value;
      
      // If changed from upvote to downvote, remove points
      if ($originalVoteValue == 1 && $newVoteValue != 1) {
        PointsService::onVoteRemoved($vote->topic->user_id);
      }
      // If changed from downvote to upvote, add points
      elseif ($originalVoteValue != 1 && $newVoteValue == 1) {
        PointsService::onVoteReceived($vote->topic->user_id);
      }
    });
  }
}
