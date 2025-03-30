<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TopicComment extends Model
{
    use HasFactory;

    protected $table = 'cyo_topic_comments';

    protected $fillable = [
        'replying_to',
        'topic_id',
        'user_id',
        'comment',
    ];

    // Define the relationship with Topic
    public function topic()
    {
        return $this->belongsTo(Topic::class, 'topic_id');
    }

    // Define the relationship with AuthAccount
    public function user()
    {
        return $this->belongsTo(AuthAccount::class, 'user_id');
    }

    // Define the relationship with TopicCommentVote
    public function votes()
    {
        return $this->hasMany(TopicCommentVote::class, 'comment_id');
    }

    public function replies()
    {
        return $this->hasMany(TopicComment::class, 'replying_to')->with(['user.profile', 'votes.user'])->orderBy('created_at', 'asc'); // Recursive
    }
}
