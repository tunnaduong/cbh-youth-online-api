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

    public function comments()
    {
        return $this->hasManyThrough(
            TopicComment::class,
            Topic::class,
            'subforum_id', // Foreign key on topics table
            'topic_id', // Foreign key on comments table
            'id', // Local key on subforums table
            'id' // Local key on topics table
        );
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

    public function latestVisibleTopic()
    {
        return $this->hasOne(Topic::class, 'subforum_id')
            ->visibleToCurrentUser()
            ->latestOfMany();
    }

    /**
     * Mối quan hệ để lấy BÀI VIẾT MỚI NHẤT,
     * sử dụng scope có sẵn để lọc theo quyền xem.
     */
    public function latestTopic()
    {
        return $this->hasOne(Topic::class, 'subforum_id')
            ->ofMany(['created_at' => 'max'], function ($query) {
                $query->visibleToCurrentUser();
            });
    }

    /**
     * Mối quan hệ để lấy BÀI VIẾT MỚI NHẤT CHỈ CÓ QUYỀN XEM PUBLIC,
     * chỉ hiển thị những bài viết public trong forum homepage.
     */
    public function latestPublicTopic()
    {
        return $this->hasOne(Topic::class, 'subforum_id')
            ->ofMany(['created_at' => 'max'], function ($query) {
                $query->publicOnly();
            });
    }


    public function getRouteKeyName()
    {
        return 'slug';
    }
}
