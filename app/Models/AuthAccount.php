<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use App\Notifications\CustomResetPasswordNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Contracts\Auth\MustVerifyEmail;

// cyo_auth_accounts model
class AuthAccount extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'cyo_auth_accounts';
    protected $fillable = ['username', 'password', 'email'];

    public function profile()
    {
        return $this->hasOne(UserProfile::class, 'auth_account_id');
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
