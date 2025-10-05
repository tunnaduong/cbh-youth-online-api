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
  protected $fillable = ['username', 'password', 'email', 'last_activity', 'role', 'provider', 'provider_id', 'provider_token', 'cached_points'];

  /**
   * The attributes that should be hidden for serialization.
   *
   * @var array<int, string>
   */
  protected $hidden = ['password'];

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
   * Get the profile associated with the user.
   *
   * @return \Illuminate\Database\Eloquent\Relations\HasOne
   */
  public function profile()
  {
    return $this->hasOne(UserProfile::class, 'auth_account_id', 'id');
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
   * @deprecated Use cached_points attribute instead for better performance
   * @return int
   */
  public function points()
  {
    // Calculate total points based on posts, likes, and comments
    $postsCount = $this->posts()->count();
    $totalLikes = $this->posts()->withCount([
      'votes' => function ($query) {
        $query->where('vote_value', 1);
      }
    ])->get()->sum('votes_count');
    $commentsCount = TopicComment::where('user_id', $this->id)->count();

    $basePoints = ($postsCount * 10) + ($totalLikes * 5) + ($commentsCount * 2);

    // Boost specific users (for testing/admin purposes)
    $boostedUsers = [
      // 'tunnaduong' => 5000,    // Add 5000 points to tunna
      // 'admin' => 10000,   // Add 10000 points to admin
    ];

    if (isset($boostedUsers[$this->username])) {
      $basePoints += $boostedUsers[$this->username];
    }

    // Subtract point deductions
    $totalDeductions = UserPointDeduction::getTotalActiveDeductions($this->id);
    $finalPoints = $basePoints - $totalDeductions;

    // Ensure points don't go below 0
    return max(0, $finalPoints);
  }

  /**
   * Get the user's cached points (recommended for performance)
   *
   * @return int
   */
  public function getCachedPoints()
  {
    return $this->cached_points ?? 0;
  }

  /**
   * Update the user's cached points
   *
   * @return bool
   */
  public function updateCachedPoints()
  {
    return PointsService::updateUserPoints($this->id);
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
}
