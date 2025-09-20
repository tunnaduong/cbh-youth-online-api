<?php

namespace App\Models;

use App\Models\Topic;
use App\Models\Follower;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Notifications\CustomResetPasswordNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

// cyo_auth_accounts model
class AuthAccount extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens;
    use HasFactory;
    use Notifiable;

    protected $table = 'cyo_auth_accounts';
    protected $fillable = ['username', 'password', 'email', 'last_activity', 'role'];
    protected $hidden = ['password'];

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

    public function profile()
    {
        return $this->hasOne(UserProfile::class, 'auth_account_id', 'id');
    }

    public function posts()
    {
        return $this->hasMany(Topic::class, 'user_id'); // Adjust 'Post' and 'user_id' as per your database schema
    }

    public function followers()
    {
        return $this->hasMany(Follower::class, 'followed_id'); // Adjust 'followed_id' as per your schema
    }

    public function following()
    {
        return $this->hasMany(Follower::class, 'follower_id'); // Adjust 'follower_id' as per your schema
    }

    public function likes()
    {
        return $this->hasMany(TopicVote::class, 'user_id'); // Adjust 'user_id' as per your schema
    }

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

        return $basePoints;
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

    // Optionally, add a method to handle email verification
    public function markEmailAsVerified()
    {
        $this->email_verified_at = now(); // Set the verification timestamp
        $this->save(); // Save the changes
    }
}
