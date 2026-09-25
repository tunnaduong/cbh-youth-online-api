<?php

namespace App\Models;

use App\Models\Topic;
use App\Models\Follower;
use App\Models\UserPointDeduction;
use App\Services\PointsService;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Notifications\CustomResetPasswordNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use App\Notifications\VerifyEmail;

/**
 * Represents a user account in the system.
 *
 * This model is responsible for user authentication, profile relationships,
 * and tracking user activity like posts, followers, and points.
 *
 * @property int $id
 * @property string $username
 * @property string $email
 * @property string $password
 * @property string|null $role
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property \Illuminate\Support\Carbon|null $last_activity
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\UserProfile|null $profile
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Topic[] $posts
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Follower[] $followers
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Follower[] $following
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\TopicVote[] $likes
 */
class AuthAccount extends Authenticatable implements MustVerifyEmail
{
  use HasApiTokens;
  use HasFactory;
  use Notifiable;

  /**
   * The table associated with the model.
   *
   * @var string
   */
  protected $table = 'cyo_auth_accounts';

  /**
   * The attributes that are mass assignable.
   *
   * @var array<int, string>
   */
  protected $fillable = ['username', 'password', 'email', 'last_activity', 'role', 'provider', 'provider_id', 'provider_token', 'points', 'email_verified_at', 'banned_at', 'banned_until', 'ban_reason', 'banned_by', 'is_ai'];

  /**
   * The attributes that should be hidden for serialization.
   *
   * @var array<int, string>
   */
  protected $hidden = ['password'];

  /**
   * The attributes that should be cast.
   *
   * @var array<string, string>
   */
  protected $casts = [
    'banned_at' => 'datetime',
    'banned_until' => 'datetime',
    'is_ai' => 'boolean',
  ];

  /**
   * Send the email verification notification.
   *
   * @return void
   */
  public function sendEmailVerificationNotification()
  {
    $this->notify(new VerifyEmail);
  }

  /**
   * Scope a query to only include users of a given role.
   *
   * @param \Illuminate\Database\Eloquent\Builder $query
   * @param string $role
   * @return \Illuminate\Database\Eloquent\Builder
   */
  public function scopeRole($query, $role)
  {
    return $query->where('role', $role);
  }

  /**
   * Get the user's role.
   *
   * @return string|null
   */
  public function getRole()
  {
    return $this->role;
  }

  /**
   * Check if the user has a specific role.
   *
   * @param string $role
   * @return bool
   */
  public function hasRole(string $role): bool
  {
    return $this->role === $role;
  }

  /**
   * Scope a query to users active within the last 5 minutes - the same
   * "online" window UserController::getOnlineStatus() and
   * updateLastActivity() use (kept here instead of repeating the raw
   * subMinutes(5) comparison at each call site).
   *
   * @param \Illuminate\Database\Eloquent\Builder $query
   * @return \Illuminate\Database\Eloquent\Builder
   */
  public function scopeOnline($query)
  {
    return $query->where('last_activity', '>', now()->subMinutes(5));
  }

  /**
   * @return bool
   */
  public function isOnline(): bool
  {
    return $this->last_activity !== null && $this->last_activity > now()->subMinutes(5);
  }

  /**
   * Get the profile associated with the user.
   *
   * @return \Illuminate\Database\Eloquent\Relations\HasOne
   */
  public function profile()
  {
    return $this->hasOne(UserProfile::class, 'auth_account_id', 'id');
  }

  /**
   * Absolute avatar URL, versioned by the profile's updated_at so clients
   * (mobile's expo-image especially, which caches by URL and otherwise
   * ignores the avatar endpoint's no-cache header) get a new URL - and so
   * bust their cache - the moment this user's avatar changes, instead of
   * showing whoever's stale cached image indefinitely.
   */
  public function avatarUrl(): string
  {
    $version = $this->profile?->updated_at?->timestamp;
    $base = config('app.url') . "/v1.0/users/{$this->username}/avatar";
    return $version ? "{$base}?v={$version}" : $base;
  }

  /**
   * Same versioning as avatarUrl(), for the cover photo.
   */
  public function coverUrl(): string
  {
    $version = $this->profile?->updated_at?->timestamp;
    $base = config('app.url') . "/v1.0/users/{$this->username}/cover";
    return $version ? "{$base}?v={$version}" : $base;
  }

  /**
   * Get the posts for the user.
   *
   * @return \Illuminate\Database\Eloquent\Relations\HasMany
   */
  public function posts()
  {
    return $this->hasMany(Topic::class, 'user_id'); // Adjust 'Post' and 'user_id' as per your database schema
  }

  /**
   * Get the followers of the user.
   *
   * @return \Illuminate\Database\Eloquent\Relations\HasMany
   */
  public function followers()
  {
    return $this->hasMany(Follower::class, 'followed_id'); // Adjust 'followed_id' as per your schema
  }

  /**
   * Get the users that this user is following.
   *
   * @return \Illuminate\Database\Eloquent\Relations\HasMany
   */
  public function following()
  {
    return $this->hasMany(Follower::class, 'follower_id'); // Adjust 'follower_id' as per your schema
  }

  /**
   * Get the likes made by the user.
   *
   * @return \Illuminate\Database\Eloquent\Relations\HasMany
   */
  public function likes()
  {
    return $this->hasMany(TopicVote::class, 'user_id'); // Adjust 'user_id' as per your schema
  }

  /**
   * Calculate the user's activity points.
   *
   * @deprecated Use points attribute instead. Points are now stored directly in database.
   * @return int
   */
  public function calculatePoints()
  {
    // This method is deprecated. Use $user->points or $user->getPoints() instead.
    return $this->points ?? 0;
  }

  /**
   * Get the user's points (stored directly in database)
   *
   * @return int
   */
  public function getPoints()
  {
    return $this->points ?? 0;
  }

  /**
   * Member tiers based on total accumulated points.
   * Returns tier info: id, name, badge_key, min_points, privileges.
   */
  public static function tiers(): array
  {
    return [
      [
        'id' => 'trainee',
        'name' => 'Thành viên tập sự',
        'badge_key' => 'badge_trainee',
        'min_points' => 50,
        'privileges' => [
          'custom_profile',
          'highlighted_name',
          'avatar_frame_trainee',
          'can_request_feature',
          'giftshop_discount_50_first_time',
        ],
      ],
      [
        'id' => 'active',
        'name' => 'Thành viên tích cực',
        'badge_key' => 'badge_active',
        'min_points' => 150,
        'privileges' => [
          'gift_points_to_others',
          'increased_post_limit',
          'priority_comment_display',
        ],
      ],
      [
        'id' => 'distinguished',
        'name' => 'Thành viên tiêu biểu',
        'badge_key' => 'badge_distinguished',
        'min_points' => 500,
        'privileges' => [
          'redeem_points_to_cash',
          'post_without_approval',
        ],
      ],
      [
        'id' => 'veteran',
        'name' => 'Thành viên kỳ cựu',
        'badge_key' => 'badge_veteran',
        'min_points' => 1000,
        'privileges' => [],
      ],
    ];
  }

  /**
   * Get current member tier based on total accumulated points.
   * Returns the highest tier the user qualifies for, or null if below 50 pts.
   */
  public function getMemberTier(): ?array
  {
    $points = $this->getPoints();
    $current = null;
    foreach (self::tiers() as $tier) {
      if ($points >= $tier['min_points']) {
        $current = $tier;
      }
    }
    return $current;
  }

  /**
   * Check if user has a specific privilege from their tier (or higher tiers).
   */
  public function hasPrivilege(string $privilege): bool
  {
    $points = $this->getPoints();
    foreach (self::tiers() as $tier) {
      if ($points >= $tier['min_points'] && in_array($privilege, $tier['privileges'])) {
        return true;
      }
    }
    return false;
  }

  /**
   * Send the password reset notification.
   *
   * @param  string  $token
   * @return void
   */
  public function sendPasswordResetNotification($token)
  {
    $this->notify(new CustomResetPasswordNotification($token));
  }

  /**
   * Mark the user's email as verified.
   *
   * @return void
   */
  public function markEmailAsVerified()
  {
    $this->email_verified_at = now(); // Set the verification timestamp
    $this->save(); // Save the changes
  }

  /**
   * Determine whether the account is currently banned.
   *
   * A user is currently banned iff banned_at is set AND either
   * banned_until is null (permanent ban) or banned_until is in the future.
   *
   * @return bool
   */
  public function isCurrentlyBanned(): bool
  {
    if (!$this->banned_at) {
      return false;
    }

    if ($this->banned_until === null) {
      return true;
    }

    return $this->banned_until->isFuture();
  }

  /**
   * Get the admin account that issued the ban, if any.
   *
   * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
   */
  public function banner()
  {
    return $this->belongsTo(AuthAccount::class, 'banned_by');
  }
}
