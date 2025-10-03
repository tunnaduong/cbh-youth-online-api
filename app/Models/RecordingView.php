<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Represents a view on a recording.
 *
 * @property int $id
 * @property int $record_id
 * @property int|null $user_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Recording $recording
 * @property-read \App\Models\AuthAccount|null $user
 */
class RecordingView extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'cyo_recording_views';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'record_id',
        'user_id',
        'created_at',
        'updated_at'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Get the recording that was viewed.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function recording()
    {
        return $this->belongsTo(Recording::class, 'record_id');
    }

    /**
     * Get the user who viewed the recording.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(AuthAccount::class, 'user_id');
    }
}
