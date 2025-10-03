<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Represents a follow relationship between two users.
 *
 * @property int $id
 * @property int $follower_id The ID of the user who is following.
 * @property int $followed_id The ID of the user who is being followed.
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\AuthAccount $follower
 * @property-read \App\Models\AuthAccount $followed
 * @property-read \App\Models\UserProfile $profile
 */
class Follower extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'cyo_user_followers'; // Table name

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = ['follower_id', 'followed_id'];
    // public $timestamps = false;

    /**
     * Get the user who is the follower.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function follower()
    {
        return $this->belongsTo(AuthAccount::class, 'follower_id');
    }

    /**
     * Get the user who is being followed.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function followed()
    {
        return $this->belongsTo(AuthAccount::class, 'followed_id');
    }

    /**
     * Get the profile of the followed user.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function profile()
    {
        return $this->belongsTo(\App\Models\UserProfile::class, 'followed_id');
    }
}
