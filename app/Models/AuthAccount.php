<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use App\Notifications\CustomResetPasswordNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

// cyo_auth_accounts model
class AuthAccount extends Authenticatable
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
}
