<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Represents a push notification subscription for a user.
 *
 * @property int $id
 * @property int $user_id
 * @property string $endpoint
 * @property string $p256dh
 * @property string $auth
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\AuthAccount $user
 */
class NotificationSubscription extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'cyo_notification_subscriptions';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'endpoint',
        'p256dh',
        'auth',
        'expires_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'expires_at' => 'datetime',
    ];

    /**
     * Get the user that owns the subscription.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(AuthAccount::class, 'user_id');
    }

    /**
     * Check if the subscription is expired.
     *
     * @return bool
     */
    public function isExpired(): bool
    {
        if ($this->expires_at === null) {
            return false; // No expiration date means it's valid
        }

        return $this->expires_at->isPast();
    }

    /**
     * Check if the subscription is valid (not expired).
     *
     * @return bool
     */
    public function isValid(): bool
    {
        return !$this->isExpired();
    }

    /**
     * Get the subscription keys as an array.
     *
     * @return array
     */
    public function getKeys(): array
    {
        return [
            'p256dh' => $this->p256dh,
            'auth' => $this->auth,
        ];
    }
}
