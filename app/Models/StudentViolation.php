<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentViolation extends Model
{
    use HasFactory;

    protected $table = 'cyo_school_mistake_list';

    protected $fillable = [
        'description',
        'mistake_type',
        'point_penalty',
        'student_id',
        'reporter_id'
    ];

    // Các loại vi phạm được định nghĩa sẵn
    const MISTAKE_TYPES = [
        'Đi muộn',
        'Vắng mặt',
        'Ra ngoài',
        'Trang phục',
        'Tập trung',
        'Trật tự',
        'Hành vi',
        'An toàn giao thông',
        'Vệ sinh',
        'Trực nhật'
    ];

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function scopeByType($query, $type)
    {
        return $query->where('mistake_type', $type);
    }
}
