<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Represents a school class.
 *
 * @property int $id
 * @property string $name
 * @property int $grade_level
 * @property int|null $main_teacher_id
 * @property int $student_count
 * @property string|null $school_year
 * @property string|null $room_number
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User|null $mainTeacher
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Schedule[] $schedules
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\MonitorReport[] $monitorReports
 */
class SchoolClass extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'cyo_school_classes';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'grade_level',
        'main_teacher_id',
        'student_count',
        'school_year',
        'room_number'
    ];

    /**
     * Get the main teacher for the class.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function mainTeacher()
    {
        return $this->belongsTo(User::class, 'main_teacher_id');
    }

    /**
     * Get the schedules for the class.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function schedules()
    {
        return $this->hasMany(Schedule::class, 'class_id');
    }

    /**
     * Get the monitor reports for the class.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function monitorReports()
    {
        return $this->hasMany(MonitorReport::class, 'class_id');
    }

    /**
     * Scope a query to only include classes of a specific grade level.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  int  $grade
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByGrade($query, $grade)
    {
        return $query->where('grade_level', $grade);
    }

    /**
     * Scope a query to only include classes of a specific school year.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $year
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeBySchoolYear($query, $year)
    {
        return $query->where('school_year', $year);
    }
}
