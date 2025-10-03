<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Represents a daily report submitted by a monitor.
 *
 * @property int $id
 * @property int $volunteer_id
 * @property int $class_id
 * @property int $shift
 * @property bool $cleanliness
 * @property bool $uniform
 * @property bool $discipline
 * @property int|null $absent
 * @property int|null $mistake_id
 * @property string|null $note
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User $monitor
 * @property-read \App\Models\SchoolClass $class
 * @property-read \App\Models\StudentViolation|null $violation
 */
class MonitorReport extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'cyo_volunteer_daily_reports';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
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

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'cleanliness' => 'boolean',
        'uniform' => 'boolean',
        'discipline' => 'boolean'
    ];

    /**
     * Get the monitor (user) who submitted the report.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function monitor()
    {
        return $this->belongsTo(User::class, 'volunteer_id');
    }

    /**
     * Get the class that the report is for.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function class()
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    /**
     * Get the violation associated with the report, if any.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function violation()
    {
        return $this->belongsTo(StudentViolation::class, 'mistake_id');
    }
}
