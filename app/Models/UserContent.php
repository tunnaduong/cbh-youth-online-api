<?php

namespace App\Models;

use App\Services\MediaThumbnailService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Represents a piece of user-uploaded content, such as an image or file.
 *
 * @property int $id
 * @property int $user_id
 * @property string $file_name
 * @property string $file_path
 * @property string|null $thumbnail_path
 * @property string|null $preview_path
 * @property string $file_type
 * @property int $file_size
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class UserContent extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = "cyo_cdn_user_content";

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id', 'file_name', 'file_path', 'thumbnail_path', 'preview_path',
        'file_type', 'file_size', 'video_status', 'photo_status',
    ];

    protected $appends = ['thumbnail_url', 'preview_url'];

    /**
     * Small (480px) preview - a downscaled copy for images, a first-frame
     * JPG for videos. Falls back to the full file for older rows/failed
     * generation, so callers can always just use this field.
     */
    public function getThumbnailUrlAttribute(): ?string
    {
        return $this->thumbnail_path
            ? MediaThumbnailService::absoluteUrl($this->thumbnail_path)
            : (\Illuminate\Support\Str::startsWith($this->file_type ?? '', 'video/') ? null : $this->getFileUrlAttribute());
    }

    /**
     * Small muted low-bitrate clip for inline/feed video autoplay - null for
     * images, and null for videos until the async job finishes (see
     * ProcessImageCompression/ProcessVideoCompression dispatch sites), in
     * which case callers should fall back to the full file_path.
     */
    public function getPreviewUrlAttribute(): ?string
    {
        return $this->preview_path ? MediaThumbnailService::absoluteUrl($this->preview_path) : null;
    }

    public function getFileUrlAttribute(): ?string
    {
        return $this->file_path ? MediaThumbnailService::absoluteUrl($this->file_path) : null;
    }
}
