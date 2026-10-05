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
        'access_token_id',
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
     * Whether subscriptions can be linked to the login that made them (the
     * access_token_id column; false until its migration has run).
     */
    public static function linksToLogin(): bool
    {
        static $exists = null;

        try {
            return $exists ??= \Illuminate\Support\Facades\Schema::hasColumn('cyo_notification_subscriptions', 'access_token_id');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Stop pushing to the browsers of these logins (personal_access_tokens
     * ids): they were logged out or revoked. Never fails the caller.
     *
     * @param  int[]  $accessTokenIds
     */
    public static function removeForLogins(array $accessTokenIds): void
    {
        $ids = array_values(array_filter(array_map('intval', $accessTokenIds)));
        if (empty($ids) || !self::linksToLogin()) {
            return;
        }

        try {
            self::whereIn('access_token_id', $ids)->delete();
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Stop pushing to every browser of a user: all their logins were ended.
     * Never fails the caller.
     */
    public static function removeForUser(int $userId): void
    {
        try {
            self::where('user_id', $userId)->delete();
        } catch (\Throwable $e) {
            report($e);
        }
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
