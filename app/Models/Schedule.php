<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Represents a school schedule entry.
 *
 * @property int $id
 * @property int $class_id
 * @property string $subject
 * @property int|null $teacher_id
 * @property string $day_of_week
 * @property \Illuminate\Support\Carbon $start_time
 * @property \Illuminate\Support\Carbon $end_time
 * @property string|null $room_number
 * @property int|null $semester
 * @property string|null $school_year
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\SchoolClass $class
 * @property-read \App\Models\User|null $teacher
 */
class Schedule extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'cyo_school_timetables';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'class_id',
        'subject',
        'teacher_id',
        'day_of_week',
        'start_time',
        'end_time',
        'room_number',
        'semester',
        'school_year',
        'notes'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i'
    ];

    /**
     * Get the class that the schedule belongs to.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function class()
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    /**
     * Get the teacher for the scheduled class.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /**
     * Scope a query to only include schedules for a specific day.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $day
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByDay($query, $day)
    {
        return $query->where('day_of_week', $day);
    }

    /**
     * Scope a query to only include schedules for a specific school year.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $year
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeBySchoolYear($query, $year)
    {
        return $query->where('school_year', $year);
    }

    /**
     * Scope a query to only include schedules for a specific semester.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  int  $semester
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeBySemester($query, $semester)
    {
        return $query->where('semester', $semester);
    }
}
