<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Represents a user's profile information.
 *
 * @property int $id
 * @property int $auth_account_id
 * @property string|null $profile_name
 * @property string|null $profile_username
 * @property string|null $bio
 * @property int|null $profile_picture
 * @property string|null $oauth_profile_picture
 * @property \Illuminate\Support\Carbon|null $birthday
 * @property string|null $gender
 * @property string|null $location
 * @property bool $verified
 * @property \Illuminate\Support\Carbon|null $last_username_change
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\AuthAccount $user
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\TopicComment[] $comments
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\TopicVote[] $votes
 */
class UserProfile extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'cyo_user_profiles';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = ['auth_account_id', 'profile_name', 'profile_username', 'bio', 'profile_picture', 'oauth_profile_picture', 'birthday', 'gender', 'location', 'verified', 'last_username_change'];

    /**
     * Get the user account that owns the profile.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(AuthAccount::class, 'auth_account_id');
    }

    /**
     * Get the comments for the user profile.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function comments()
    {
        return $this->hasMany(TopicComment::class);
    }

    /**
     * Get the topic votes for the user profile.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function votes()
    {
        return $this->hasMany(TopicVote::class);
    }
}
