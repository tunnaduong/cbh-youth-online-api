<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuthEmailVerificationCode extends Model
{
    use HasFactory;

    // Define the table name if it's not the plural of the model name
    protected $table = 'cyo_auth_email_verification_code';

    // Define the fillable fields
    protected $fillable = [
        'user_id',
        'verification_code',
        'created_at',
        'expires_at',
    ];

    // Define the relationship with the User model
    public function user()
    {
        return $this->belongsTo(AuthAccount::class);
    }
}
