<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TopicCommentVote extends Model
{
    use HasFactory;

    protected $table = 'cyo_topic_comment_votes';

    protected $fillable = [
        'comment_id',
        'user_id',
        'vote_value', // Assuming a value for upvote (1) and downvote (-1)
    ];

    // Define the relationship with TopicComment
    public function comment()
    {
        return $this->belongsTo(TopicComment::class, 'comment_id');
    }

    // Define the relationship with AuthAccount
    public function user()
    {
        return $this->belongsTo(AuthAccount::class, 'user_id');
    }
}
