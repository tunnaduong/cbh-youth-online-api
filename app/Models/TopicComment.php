<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Represents a comment on a topic.
 *
 * @property int $id
 * @property int|null $replying_to The ID of the comment this is a reply to.
 * @property int $topic_id
 * @property int $user_id
 * @property string $comment
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Topic $topic
 * @property-read \App\Models\AuthAccount $user
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\TopicCommentVote[] $votes
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\TopicComment[] $replies
 */
class TopicComment extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'cyo_topic_comments';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'replying_to',
        'topic_id',
        'user_id',
        'comment',
    ];

    /**
     * Get the topic that the comment belongs to.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function topic()
    {
        return $this->belongsTo(Topic::class, 'topic_id');
    }

    /**
     * Get the user who created the comment.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(AuthAccount::class, 'user_id');
    }

    /**
     * Get the votes for the comment.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function votes()
    {
        return $this->hasMany(TopicCommentVote::class, 'comment_id');
    }

    /**
     * Get the replies for the comment.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function replies()
    {
        return $this->hasMany(TopicComment::class, 'replying_to')->with(['user.profile', 'votes.user'])->orderBy('created_at', 'asc'); // Recursive
    }
}
