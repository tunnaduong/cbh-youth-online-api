<?php

namespace App\Models;

use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class Topic extends Model
{
    use HasFactory;

    protected $table = 'cyo_topics';

    // Define which fields are mass assignable
    protected $fillable = [
        'subforum_id',
        'user_id',
        'title',
        'description',
        'content_html',
        'pinned',
        'cdn_image_id',
        'hidden',
        'anonymous',
    ];

    protected $appends = ['content'];

    // Define the relationship: A topic belongs to a user
    public function user()
    {
        return $this->belongsTo(AuthAccount::class, 'user_id');
    }

    public function author()
    {
        return $this->belongsTo(AuthAccount::class, 'user_id'); // Adjust 'user_id' if necessary
    }

    public function views()
    {
        return $this->hasMany(TopicView::class, 'topic_id'); // Adjust 'topic_id' if necessary
    }

    public function votes()
    {
        return $this->hasMany(TopicVote::class, 'topic_id');
    }

    public function comments()
    {
        return $this->hasMany(TopicComment::class, 'topic_id'); // Adjust 'topic_id' if necessary
    }

    public function isPinned()
    {
        return $this->pinned;
    }

    public function subforum()
    {
        return $this->belongsTo(ForumSubforum::class, 'subforum_id');
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

    // Define the relationship with UserContent for multiple images
    public function cdnUserContent()
    {
        if (empty($this->cdn_image_id)) {
            return $this->hasOne(UserContent::class, 'id', 'cdn_image_id');
        }

        $imageIds = array_filter(explode(',', $this->cdn_image_id));
        return $this->hasOne(UserContent::class, 'id', 'cdn_image_id')
            ->whereIn('id', $imageIds);
    }

    // Helper method to get image URLs
    public function getImageUrls()
    {
        if (empty($this->cdn_image_id)) {
            return collect([]);
        }

        $imageIds = array_filter(explode(',', $this->cdn_image_id));
        return UserContent::whereIn('id', $imageIds)
            ->orderByRaw("FIELD(id, " . implode(',', $imageIds) . ")")
            ->get();
    }

    public function getImageUrlsAttribute()
    {
        return $this->getImageUrls()->map(function ($content) {
            return 'https://api.chuyenbienhoa.com' . Storage::url($content->file_path);
        })->all();
    }

    public function getContentAttribute()
    {
        return $this->content_html;
    }

    // khi query withCount('comments'), Laravel sẽ gắn vào thuộc tính comments_count
    public function getCommentsCountAttribute($value)
    {
        return $this->roundToNearestFive($value) . "+";
    }

    public function getCreatedAtHumanAttribute($value)
    {
        return $this->created_at
            ? $this->created_at->diffForHumans()
            : null;
    }

    private function roundToNearestFive($count)
    {
        if ($count <= 5) {
            // Nếu <= 5 thì thêm số 0 phía trước
            return str_pad($count, 2, '0', STR_PAD_LEFT);
        } else {
            // Làm tròn xuống bội số của 5 và pad 2 chữ số
            return str_pad(floor($count / 5) * 5, 2, '0', STR_PAD_LEFT);
        }
    }

    /**
     * Get the URL slug for the topic
     */
    public function getSlug()
    {
        $slug = Str::slug($this->title, '-', 'vi');
        return empty($slug) ? 'untitled' : $slug;
    }

    /**
     * Get the URL for the topic
     */
    public function getUrl()
    {
        return "/{$this->user->username}/posts/{$this->id}-" . $this->getSlug();
    }
}
