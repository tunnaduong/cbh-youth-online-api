<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

/**
 * Represents an Expo push token for a user.
 *
 * @property int $id
 * @property int $user_id
 * @property string $expo_push_token
 * @property string|null $device_type
 * @property string|null $device_id
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $last_used_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\AuthAccount $user
 */
class ExpoPushToken extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'cyo_expo_push_tokens';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'expo_push_token',
        'device_type',
        'device_id',
        'is_active',
        'last_used_at',
        'access_token_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
    ];

    /**
     * Get the user that owns the token.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(AuthAccount::class, 'user_id');
    }

    /**
     * Whether push tokens can be linked to the login that registered them
     * (the access_token_id column; false until its migration has run).
     */
    public static function linksToLogin(): bool
    {
        static $exists = null;

        try {
            return $exists ??= \Illuminate\Support\Facades\Schema::hasColumn('cyo_expo_push_tokens', 'access_token_id');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Stop pushing to the devices of these logins (personal_access_tokens
     * ids): they were logged out or revoked. Never fails the caller.
     *
     * @param  int[]  $accessTokenIds
     */
    public static function deactivateForLogins(array $accessTokenIds): void
    {
        $ids = array_values(array_filter(array_map('intval', $accessTokenIds)));
        if (empty($ids) || !self::linksToLogin()) {
            return;
        }

        try {
            self::whereIn('access_token_id', $ids)->update(['is_active' => false]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Stop pushing to every device of a user: all their logins were ended
     * (password reset by an admin, account deleted). Never fails the caller.
     */
    public static function deactivateForUser(int $userId): void
    {
        try {
            self::where('user_id', $userId)->update(['is_active' => false]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Scope a query to only include active tokens.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include tokens for a specific user.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  int  $userId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Mark the token as used (update last_used_at).
     *
     * @return void
     */
    public function markAsUsed(): void
    {
        $this->update(['last_used_at' => now()]);
    }

    /**
     * Deactivate the token.
     *
     * @return bool
     */
    public function deactivate(): bool
    {
        return $this->update(['is_active' => false]);
    }

    /**
     * Activate the token.
     *
     * @return bool
     */
    public function activate(): bool
    {
        return $this->update(['is_active' => true]);
    }
}


