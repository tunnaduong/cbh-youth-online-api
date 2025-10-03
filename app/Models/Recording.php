<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\AuthAccount;
use App\Models\UserContent;
use App\Models\RecordingView;
use Illuminate\Support\Facades\Storage;

/**
 * Represents an audio recording.
 *
 * @property int $id
 * @property string $title
 * @property string|null $description
 * @property string|null $content_html
 * @property int|null $cdn_audio_id
 * @property int|null $cdn_preview_id
 * @property string|null $audio_length
 * @property int $user_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\AuthAccount $author
 * @property-read \App\Models\UserContent|null $cdnAudio
 * @property-read \App\Models\UserContent|null $cdnPreview
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\RecordingView[] $views
 * @property-read string|null $audio_url
 * @property-read string|null $preview_image_url
 * @property-read int $view_count
 * @property-read string|null $created_at_human
 * @property-read string|null $content
 */
class Recording extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'cyo_recordings';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'title',
        'description',
        'content_html',
        'cdn_audio_id',
        'cdn_preview_id',
        'audio_length',
        'user_id'
    ];

    /**
     * Get the user that owns the recording.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function author()
    {
        return $this->belongsTo(AuthAccount::class, 'user_id');
    }

    /**
     * Get the audio file for this recording.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function cdnAudio()
    {
        return $this->belongsTo(UserContent::class, 'cdn_audio_id');
    }

    /**
     * Get the preview image for this recording.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function cdnPreview()
    {
        return $this->belongsTo(UserContent::class, 'cdn_preview_id');
    }

    /**
     * Get the views for this recording.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function views()
    {
        return $this->hasMany(RecordingView::class, 'record_id');
    }

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'audio_length' => 'string', // Duration is stored as HH:MM:SS
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Get the audio URL for this recording.
     *
     * @return string|null
     */
    public function getAudioUrlAttribute()
    {
        if ($this->cdnAudio) {
            return 'https://api.chuyenbienhoa.com' . Storage::url($this->cdnAudio->file_path);
        }
        return null;
    }

    /**
     * Get the preview image URL for this recording.
     *
     * @return string|null
     */
    public function getPreviewImageUrlAttribute()
    {
        if ($this->cdnPreview) {
            return 'https://api.chuyenbienhoa.com' . Storage::url($this->cdnPreview->file_path);
        }
        return null;
    }

    /**
     * Get the view count for this recording.
     *
     * @return int
     */
    public function getViewCountAttribute()
    {
        return $this->views()->count();
    }

    /**
     * Get human-readable created date.
     *
     * @return string|null
     */
    public function getCreatedAtHumanAttribute()
    {
        return $this->created_at ? $this->created_at->diffForHumans() : null;
    }

    /**
     * Get the content attribute (HTML version if available, otherwise description).
     *
     * @return string|null
     */
    public function getContentAttribute()
    {
        return $this->content_html ?: $this->description;
    }
}
