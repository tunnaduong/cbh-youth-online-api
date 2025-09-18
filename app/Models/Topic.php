<?php

namespace App\Models;

use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Collection;

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
  ];

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
}
