<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserProfile extends Model
{
    use HasFactory;

    protected $table = 'cyo_user_profiles'; // The table name associated with this model
    protected $fillable = ['auth_account_id', 'profile_name', 'profile_username', 'bio', 'profile_picture', 'birthday', 'gender', 'location']; // Example of fillable fields

    public function authAccount()
    {
        return $this->belongsTo(AuthAccount::class, 'auth_account_id');
    }

    public function comments()
    {
        return $this->hasMany(TopicComment::class);
    }

    public function votes()
    {
        return $this->hasMany(TopicVote::class);
    }
}
