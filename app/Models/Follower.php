<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Follower extends Model
{
    use HasFactory;

    protected $table = 'cyo_user_followers'; // Table name
    protected $fillable = ['follower_id', 'followed_id'];
    // public $timestamps = false;

    // Relationship to the follower (user who follows)
    public function follower()
    {
        return $this->belongsTo(AuthAccount::class, 'follower_id');
    }

    // Relationship to the followed (user being followed)
    public function followed()
    {
        return $this->belongsTo(AuthAccount::class, 'followed_id');
    }

    public function profile()
    {
        return $this->belongsTo(\App\Models\UserProfile::class, 'followed_id');
    }
}
