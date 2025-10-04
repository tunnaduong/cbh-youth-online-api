<?php

namespace App\Models;

use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

/**
 * Represents a topic or post in the forum.
 *
 * @property int $id
 * @property int|null $subforum_id
 * @property int $user_id
 * @property string $title
 * @property string $description The raw markdown content.
 * @property string|null $content_html The rendered HTML content.
 * @property bool $pinned
 * @property string|null $cdn_image_id Comma-separated string of UserContent IDs.
 * @property bool $hidden
 * @property bool $anonymous
 * @property string $privacy
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\AuthAccount $user
 * @property-read \App\Models\AuthAccount $author
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\TopicView[] $views
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\TopicVote[] $votes
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\TopicComment[] $comments
 * @property-read \App\Models\ForumSubforum|null $subforum
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\AuthAccount[] $savedTopics
 * @property-read \App\Models\UserContent|null $cdnUserContent
 * @property-read array $image_urls
 * @property-read string|null $content
 * @property-read string $comments_count
 * @property-read string|null $created_at_human
 * @property-read array $votes_formatted
 */
class Topic extends Model
{
  use HasFactory;

  /**
   * The table associated with the model.
   *
   * @var string
   */
  protected $table = 'cyo_topics';

  /**
   * Indicates if the IDs are auto-incrementing.
   *
   * @var bool
   */
  public $incrementing = true;

  /**
   * The "type" of the primary key ID.
   *
   * @var string
   */
  protected $keyType = 'int';

  /**
   * The attributes that are mass assignable.
   *
   * @var array<int, string>
   */
  protected $fillable = [
    'subforum_id',
    'user_id',
    'title',
    'description',
    'content_html',
    'pinned',
    'cdn_image_id',
    'cdn_document_id',
    'hidden',
    'anonymous',
    'privacy',
  ];

  /**
   * The accessors to append to the model's array form.
   *
   * @var array<int, string>
   */
  protected $appends = ['content', 'document_urls'];

  /**
   * Get the user that owns the topic.
   *
   * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
   */
  public function user()
  {
    return $this->belongsTo(AuthAccount::class, 'user_id');
  }

  /**
   * Get the author of the topic (alias for user).
   *
   * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
   */
  public function author()
  {
    return $this->belongsTo(AuthAccount::class, 'user_id'); // Adjust 'user_id' if necessary
  }

  /**
   * Get the views for the topic.
   *
   * @return \Illuminate\Database\Eloquent\Relations\HasMany
   */
  public function views()
  {
    return $this->hasMany(TopicView::class, 'topic_id'); // Adjust 'topic_id' if necessary
  }

  /**
   * Get the votes for the topic.
   *
   * @return \Illuminate\Database\Eloquent\Relations\HasMany
   */
  public function votes()
  {
    return $this->hasMany(TopicVote::class, 'topic_id');
  }

  /**
   * Get the comments for the topic.
   *
   * @return \Illuminate\Database\Eloquent\Relations\HasMany
   */
  public function comments()
  {
    return $this->hasMany(TopicComment::class, 'topic_id'); // Adjust 'topic_id' if necessary
  }

  /**
   * Check if the topic is pinned.
   *
   * @return bool
   */
  public function isPinned()
  {
    return $this->pinned;
  }

  /**
   * Get the subforum that the topic belongs to.
   *
   * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
   */
  public function subforum()
  {
    return $this->belongsTo(ForumSubforum::class, 'subforum_id');
  }

  /**
   * Check if the topic is saved by a specific user.
   *
   * @param  int  $userId
   * @return bool
   */
  public function isSavedByUser($userId)
  {
    return $this->savedTopics()->where('user_id', $userId)->exists();
  }

  /**
   * The users that have saved the topic.
   *
   * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
   */
  public function savedTopics()
  {
    // Adjust the relationship to match your database structure
    return $this->belongsToMany(AuthAccount::class, 'cyo_user_saved_topics', 'topic_id', 'user_id');
    // Make sure to specify the local key and foreign key if they differ from default naming conventions
  }

  /**
   * Get the user content (images) associated with the topic.
   *
   * @return \Illuminate\Database\Eloquent\Relations\HasOne
   */
  public function cdnUserContent()
  {
    if (empty($this->cdn_image_id)) {
      return $this->hasOne(UserContent::class, 'id', 'cdn_image_id');
    }

    $imageIds = array_filter(explode(',', $this->cdn_image_id));
    return $this->hasOne(UserContent::class, 'id', 'cdn_image_id')
      ->whereIn('id', $imageIds);
  }

  /**
   * Helper method to get an ordered collection of image models.
   *
   * @return \Illuminate\Database\Eloquent\Collection
   */
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

  /**
   * Get the image URLs for the topic.
   *
   * @return array
   */
  public function getImageUrlsAttribute()
  {
    return $this->getImageUrls()->map(function ($content) {
      return 'https://api.chuyenbienhoa.com' . Storage::url($content->file_path);
    })->all();
  }

  /**
   * Helper method to get an ordered collection of document models.
   *
   * @return \Illuminate\Database\Eloquent\Collection
   */
  public function getDocuments()
  {
    if (empty($this->cdn_document_id)) {
      return collect([]);
    }

    $documentIds = array_filter(explode(',', $this->cdn_document_id));
    return UserContent::whereIn('id', $documentIds)
      ->orderByRaw("FIELD(id, " . implode(',', $documentIds) . ")")
      ->get();
  }

  /**
   * Get the document URLs for the topic.
   *
   * @return array
   */
  public function getDocumentUrlsAttribute()
  {
    return $this->getDocuments()->map(function ($content) {
      return 'https://api.chuyenbienhoa.com' . Storage::url($content->file_path);
    })->all();
  }

  /**
   * Get the HTML content of the topic.
   *
   * @return string|null
   */
  public function getContentAttribute()
  {
    return $this->content_html;
  }

  /**
   * Get the formatted comment count.
   *
   * @param  int  $value
   * @return string
   */
  public function getCommentsCountAttribute($value)
  {
    return $this->roundToNearestFive($value) . "+";
  }

  /**
   * Get the created_at timestamp in a human-readable format.
   *
   * @param  mixed  $value
   * @return string|null
   */
  public function getCreatedAtHumanAttribute($value)
  {
    return $this->created_at
      ? $this->created_at->diffForHumans()
      : null;
  }

  /**
   * Round a number down to the nearest multiple of five for display.
   *
   * @param  int  $count
   * @return string
   */
  private function roundToNearestFive($count)
  {
    if ($count <= 5) {
      // If <= 5, pad with a leading zero
      return str_pad($count, 2, '0', STR_PAD_LEFT);
    } else {
      // Round down to the nearest multiple of 5 and pad to 2 digits
      return str_pad(floor($count / 5) * 5, 2, '0', STR_PAD_LEFT);
    }
  }

  /**
   * Get the URL slug for the topic.
   *
   * @return string
   */
  public function getSlug()
  {
    $slug = Str::slug($this->title, '-', 'vi');
    return empty($slug) ? 'untitled' : $slug;
  }

  /**
   * Get the full URL for the topic.
   *
   * @return string
   */
  public function getUrl()
  {
    return "/{$this->user->username}/posts/{$this->id}-" . $this->getSlug();
  }

  /**
   * Scope a query to only include topics visible to the current user.
   *
   * @param  \Illuminate\Database\Eloquent\Builder  $query
   * @return \Illuminate\Database\Eloquent\Builder
   */
  public function scopeVisibleToCurrentUser($query)
  {
    if (auth()->check()) {
      $userId = auth()->id();
      $followingIds = \App\Models\Follower::where('follower_id', $userId)
        ->pluck('followed_id')
        ->toArray();

      return $query->where(function ($q) use ($userId, $followingIds) {
        $q->where('privacy', 'public')
          // User's own posts (of any privacy)
          ->orWhere('user_id', $userId)
          // Followers-only posts from followed users
          ->orWhere(function ($subQ) use ($followingIds) {
            $subQ->where('privacy', 'followers')
              ->whereIn('user_id', $followingIds);
          });
      });
    }

    // For guests, only public posts
    return $query->where('privacy', 'public');
  }

  /**
   * Scope a query to only include public topics.
   *
   * @param  \Illuminate\Database\Eloquent\Builder  $query
   * @return \Illuminate\Database\Eloquent\Builder
   */
  public function scopePublicOnly($query)
  {
    return $query->where('privacy', 'public');
  }

  /**
   * Scope a query to only include private topics.
   *
   * @param  \Illuminate\Database\Eloquent\Builder  $query
   * @return \Illuminate\Database\Eloquent\Builder
   */
  public function scopePrivateOnly($query)
  {
    return $query->where('privacy', 'private');
  }

  /**
   * Scope a query to only include followers-only topics.
   *
   * @param  \Illuminate\Database\Eloquent\Builder  $query
   * @return \Illuminate\Database\Eloquent\Builder
   */
  public function scopeFollowersOnly($query)
  {
    return $query->where('privacy', 'followers');
  }

  /**
   * The "booted" method of the model.
   *
   * @return void
   */
  protected static function boot()
  {
    parent::boot();

    static::creating(function ($topic) {
      if (empty($topic->id)) {
        $topic->id = static::generateRandomizedId();
      }
    });
  }

  /**
   * Generate a unique randomized ID for the model.
   *
   * @return int
   */
  public static function generateRandomizedId(): int
  {
    do {
      // Generate a random number between 100000 and 999999999
      $randomizedId = rand(100000, 999999999);
    } while (static::where('id', $randomizedId)->exists());

    return $randomizedId;
  }

  /**
   * Get the formatted votes for the topic.
   *
   * @return \Illuminate\Support\Collection
   */
  public function getVotesFormattedAttribute()
  {
    return $this->votes->map(function ($vote) {
      return [
        'username' => $vote->user->username,
        'vote_value' => $vote->vote_value,
        'created_at' => $vote->created_at,
        'updated_at' => $vote->updated_at,
      ];
    });
  }
}
