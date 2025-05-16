<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MonitorReport extends Model
{
    use HasFactory;

    protected $table = 'cyo_volunteer_daily_reports';

    protected $fillable = [
        'volunteer_id',
        'class_id',
        'shift',
        'cleanliness',
        'uniform',
        'discipline',
        'absent',
        'mistake_id',
        'note'
    ];

    protected $casts = [
        'cleanliness' => 'boolean',
        'uniform' => 'boolean',
        'discipline' => 'boolean'
    ];

    public function monitor()
    {
        return $this->belongsTo(User::class, 'volunteer_id');
    }

    public function class()
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function violation()
    {
        return $this->belongsTo(StudentViolation::class, 'mistake_id');
    }
}
