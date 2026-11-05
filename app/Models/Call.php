<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Represents a voice or video call session within a conversation.
 *
 * @property int $id
 * @property int $conversation_id
 * @property int $caller_id
 * @property int|null $receiver_id
 * @property string $type 'audio' or 'video'
 * @property string $status 'ringing', 'accepted', 'rejected', 'busy', 'ended', 'missed', 'cancelled'
 * @property \Illuminate\Support\Carbon|null $started_at
 * @property \Illuminate\Support\Carbon|null $accepted_at
 * @property \Illuminate\Support\Carbon|null $ended_at
 * @property int|null $duration
 * @property string|null $end_reason
 * @property array|null $metadata
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Conversation $conversation
 * @property-read \App\Models\AuthAccount $caller
 * @property-read \App\Models\AuthAccount|null $receiver
 */
class Call extends Model
{
  /**
   * The table associated with the model.
   *
   * @var string
   */
  protected $table = 'cyo_calls';

  /**
   * The attributes that are mass assignable.
   *
   * @var array<int, string>
   */
  protected $fillable = [
    'conversation_id',
    'caller_id',
    'receiver_id',
    'type',
    'status',
    'started_at',
    'accepted_at',
    'ended_at',
    'duration',
    'end_reason',
    'metadata',
  ];

  /**
   * The attributes that should be cast.
   *
   * @var array<string, string>
   */
  protected $casts = [
    'started_at' => 'datetime',
    'accepted_at' => 'datetime',
    'ended_at' => 'datetime',
    'duration' => 'integer',
    'metadata' => 'array',
    'created_at' => 'datetime',
    'updated_at' => 'datetime',
  ];

  /**
   * Conversation this call belongs to.
   */
  public function conversation(): BelongsTo
  {
    return $this->belongsTo(Conversation::class, 'conversation_id');
  }

  /**
   * Caller who initiated the call.
   */
  public function caller(): BelongsTo
  {
    return $this->belongsTo(AuthAccount::class, 'caller_id');
  }

  /**
   * Recipient user for 1-1 calls.
   */
  public function receiver(): BelongsTo
  {
    return $this->belongsTo(AuthAccount::class, 'receiver_id');
  }

  /**
   * Check if call is currently active (ringing or connected).
   */
  public function isActive(): bool
  {
    return in_array($this->status, ['ringing', 'accepted'], true);
  }
}
