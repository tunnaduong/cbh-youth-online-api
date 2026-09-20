<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModerationQueue extends Model
{
    protected $table = 'cyo_moderation_queue';

    protected $fillable = [
        'content_type',
        'content_id',
        'user_id',
        'content_snapshot',
        'status',
        'ai_verdict',
        'ai_reason',
        'reviewed_by',
        'reviewer_note',
        'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(AuthAccount::class, 'user_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(AuthAccount::class, 'reviewed_by');
    }
}
