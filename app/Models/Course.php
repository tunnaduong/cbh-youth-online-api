<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    use HasFactory;

    protected $table = 'cyo_courses';

    protected $fillable = [
        'name',
        'description',
        'teacher_id',
        'class_id',
        'active'
    ];

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function class()
    {
        return $this->belongsTo(ClassRoom::class, 'class_id');
    }
}
