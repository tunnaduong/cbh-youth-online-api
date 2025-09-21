<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\AuthAccount;
use App\Models\UserContent;
use App\Models\RecordingView;
use Illuminate\Support\Facades\Storage;

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
     */
    public function author()
    {
        return $this->belongsTo(AuthAccount::class, 'user_id');
    }

    /**
     * Get the audio file for this recording.
     */
    public function cdnAudio()
    {
        return $this->belongsTo(UserContent::class, 'cdn_audio_id');
    }

    /**
     * Get the preview image for this recording.
     */
    public function cdnPreview()
    {
        return $this->belongsTo(UserContent::class, 'cdn_preview_id');
    }

    /**
     * Get the views for this recording.
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
     */
    public function getViewCountAttribute()
    {
        return $this->views()->count();
    }

    /**
     * Get human-readable created date.
     */
    public function getCreatedAtHumanAttribute()
    {
        return $this->created_at ? $this->created_at->diffForHumans() : null;
    }

    /**
     * Get the content attribute (HTML version if available, otherwise description).
     */
    public function getContentAttribute()
    {
        return $this->content_html ?: $this->description;
    }
}
