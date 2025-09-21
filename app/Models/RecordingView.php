<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RecordingView extends Model
{
    use HasFactory;

    protected $table = 'cyo_recording_views';

    protected $fillable = [
        'record_id',
        'user_id',
        'created_at',
        'updated_at'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Get the recording that was viewed.
     */
    public function recording()
    {
        return $this->belongsTo(Recording::class, 'record_id');
    }

    /**
     * Get the user who viewed the recording.
     */
    public function user()
    {
        return $this->belongsTo(AuthAccount::class, 'user_id');
    }
}
