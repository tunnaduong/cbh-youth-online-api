<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClassRoom extends Model
{
    use HasFactory;

    protected $table = 'cyo_classes';

    protected $fillable = [
        'name',
        'description',
        'teacher_id',
        'active'
    ];

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function students()
    {
        return $this->belongsToMany(User::class, 'cyo_class_students', 'class_id', 'student_id');
    }
}
