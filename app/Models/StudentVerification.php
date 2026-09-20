<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentVerification extends Model
{
    protected $table = 'cyo_student_verifications';

    protected $fillable = [
        'user_id',
        'selfie_url',
        'student_card_url',
        'status',
        'rejection_reason',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
