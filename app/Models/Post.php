<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use HasFactory;

    protected $table = 'cyo_forum_posts';

    protected $fillable = [
        'title',
        'slug',
        'content',
        'subforum_id',
        'author_id',
        'is_pinned',
        'is_locked',
        'last_reply_id',
        'view_count',
        'reply_count'
    ];

    protected $casts = [
        'is_pinned' => 'boolean',
        'is_locked' => 'boolean',
        'view_count' => 'integer',
        'reply_count' => 'integer'
    ];

    public function subforum()
    {
        return $this->belongsTo(Subforum::class, 'subforum_id');
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function lastReply()
    {
        return $this->belongsTo(Post::class, 'last_reply_id');
    }

    public function replies()
    {
        return $this->hasMany(Post::class, 'parent_id');
    }

    public function parent()
    {
        return $this->belongsTo(Post::class, 'parent_id');
    }

    // Scope để lấy các bài viết được ghim
    public function scopePinned($query)
    {
        return $query->where('is_pinned', true);
    }

    // Scope để lấy các bài viết bị khóa
    public function scopeLocked($query)
    {
        return $query->where('is_locked', true);
    }

    // Scope để lấy các bài viết gốc (không phải reply)
    public function scopeOriginal($query)
    {
        return $query->whereNull('parent_id');
    }

    // Scope để sắp xếp theo mới nhất
    public function scopeLatest($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    // Scope để sắp xếp theo phổ biến (view_count)
    public function scopePopular($query)
    {
        return $query->orderBy('view_count', 'desc');
    }
}
