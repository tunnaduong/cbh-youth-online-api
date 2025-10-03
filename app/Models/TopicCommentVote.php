<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Represents a vote on a topic comment.
 *
 * @property int $id
 * @property int $comment_id
 * @property int $user_id
 * @property int $vote_value Can be 1 for upvote, -1 for downvote.
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\TopicComment $comment
 * @property-read \App\Models\AuthAccount $user
 */
class TopicCommentVote extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'cyo_topic_comment_votes';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'comment_id',
        'user_id',
        'vote_value', // Assuming a value for upvote (1) and downvote (-1)
    ];

    /**
     * Get the comment that the vote belongs to.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function comment()
    {
        return $this->belongsTo(TopicComment::class, 'comment_id');
    }

    /**
     * Get the user who made the vote.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(AuthAccount::class, 'user_id');
    }
}
