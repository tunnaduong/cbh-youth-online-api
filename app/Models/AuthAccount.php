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
    protected $fillable = [
        'username',
        'password',
        'email',
        'last_activity',
        'role', // 'user','student','teacher','volunteer','admin'
    ];

    protected $hidden = ['password'];

    public function hasRole($role)
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
