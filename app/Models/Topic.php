<?php

namespace App\Models;

use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Topic extends Model
{
    use HasFactory;

    protected $table = 'cyo_topics';

    // Define which fields are mass assignable
    protected $fillable = [
        'subforum_id', // Thêm subforum_id vào đây
        'user_id',
        'title',
        'description',
        'pinned',
        'cdn_image_id', // Thêm cdn_image_id vào đây
    ];

    // Define the relationship: A topic belongs to a user
    public function user()
    {
        return $this->belongsTo(AuthAccount::class, 'user_id');
    }

    public function views()
    {
        return $this->hasMany(TopicView::class);
    }

    public function votes()
    {
        return $this->hasMany(TopicVote::class);
    }

    public function comments()
    {
        return $this->hasMany(TopicComment::class);
    }

    public function isPinned()
    {
        return $this->pinned;
    }

    public function subforum()
    {
        return $this->belongsTo(ForumSubforum::class); // Định nghĩa quan hệ với Subforum
    }

    public function isSavedByUser($userId)
    {
        return $this->savedTopics()->where('user_id', $userId)->exists();
    }

    public function savedTopics()
    {
        // Adjust the relationship to match your database structure
        return $this->belongsToMany(AuthAccount::class, 'cyo_user_saved_topics', 'topic_id', 'user_id');
        // Make sure to specify the local key and foreign key if they differ from default naming conventions
    }

    // Define the relationship with UserContent
    public function cdnUserContent()
    {
        return $this->belongsTo(UserContent::class, 'cdn_image_id');
    }
}
