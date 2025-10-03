<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Represents an email verification code for a user.
 *
 * @property int $id
 * @property int $user_id
 * @property string $verification_code
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property-read \App\Models\AuthAccount $user
 */
class AuthEmailVerificationCode extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'cyo_auth_email_verification_code';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'verification_code',
        'created_at',
        'expires_at',
    ];

    /**
     * Get the user that owns the verification code.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(AuthAccount::class);
    }
}
