<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subforum extends Model
{
    use HasFactory;

    protected $table = 'cyo_forum_subforums';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'category_id',
        'order',
        'is_active',
        'moderator_id',
        'last_post_id'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'order' => 'integer'
    ];

    public function category()
    {
        return $this->belongsTo(ForumCategory::class, 'category_id');
    }

    public function posts()
    {
        return $this->hasMany(Topic::class, 'subforum_id');
    }

    public function moderator()
    {
        return $this->belongsTo(User::class, 'moderator_id');
    }

    public function lastPost()
    {
        return $this->belongsTo(Topic::class, 'last_post_id');
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

    // Scope để lấy các diễn đàn con đang hoạt động
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Scope để sắp xếp theo thứ tự
    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }
}
