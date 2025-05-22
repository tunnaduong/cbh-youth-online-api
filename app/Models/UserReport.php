<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserReport extends Model
{
    use HasFactory;

    protected $table = 'cyo_user_reports';

    protected $fillable = [
        'user_id',
        'reported_user_id',
        'topic_id',
        'reason',
        'status', // pending, reviewed, resolved, dismissed
        'admin_notes',
        'reviewed_by',
        'reviewed_at'
    ];

    protected $casts = [
        'reviewed_at' => 'datetime'
    ];

    // Reporter user relationship
    public function reporter()
    {
        return $this->belongsTo(AuthAccount::class, 'user_id');
    }

    // Reported user relationship
    public function reportedUser()
    {
        return $this->belongsTo(AuthAccount::class, 'reported_user_id');
    }

    // Topic relationship
    public function topic()
    {
        return $this->belongsTo(Topic::class, 'topic_id');
    }

    // Admin who reviewed the report
    public function reviewedBy()
    {
        return $this->belongsTo(AuthAccount::class, 'reviewed_by');
    }
}
