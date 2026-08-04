<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One image a conversation has used as its chat background, kept around so
 * participants can pick an old one again without re-uploading.
 *
 * @property int $id
 * @property int $conversation_id
 * @property int $user_content_id
 * @property int|null $set_by
 * @property \Illuminate\Support\Carbon $used_at
 */
class ConversationBackgroundHistory extends Model
{
    protected $table = 'cyo_conversation_background_history';

    protected $fillable = ['conversation_id', 'user_content_id', 'set_by', 'used_at'];

    protected $casts = [
        'used_at' => 'datetime',
    ];

    public function content(): BelongsTo
    {
        return $this->belongsTo(UserContent::class, 'user_content_id');
    }

    public function setBy(): BelongsTo
    {
        return $this->belongsTo(AuthAccount::class, 'set_by');
    }
}
