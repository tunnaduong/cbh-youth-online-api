<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ForumSubforum extends Model
{
    protected $table = "cyo_forum_subforums";

    protected $fillable = ['main_category_id', 'name', 'description', 'active', 'pinned', 'role_restriction'];

    public function mainCategory()
    {
        return $this->belongsTo(ForumMainCategory::class, 'main_category_id');
    }

    public function moderator()
    {
        return $this->belongsTo(AuthAccount::class, 'moderator_id');
    }

    // Define the relationship to topics
    public function topics()
    {
        return $this->hasMany(Topic::class, 'subforum_id');
    }

    // Accessor to get the number of posts
    public function getPostsCountAttribute()
    {
        return $this->topics()->count();
    }

    // Accessor to get the latest post
    public function getLatestPostAttribute()
    {
        // Use orderBy with a fallback to check that 'created_at' is not null
        return $this->topics()->whereNotNull('created_at')->latest('created_at')->first();
    }

    public function latestTopic()
    {
        return $this->hasOne(Topic::class, 'subforum_id')->latestOfMany();
    }
}
