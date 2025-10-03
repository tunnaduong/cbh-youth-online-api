<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Represents a student violation record.
 *
 * @property int $id
 * @property string $description
 * @property string $mistake_type
 * @property int $point_penalty
 * @property int $student_id
 * @property int $reporter_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User $student
 * @property-read \App\Models\User $reporter
 */
class StudentViolation extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'cyo_school_mistake_list';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'description',
        'mistake_type',
        'point_penalty',
        'student_id',
        'reporter_id'
    ];

    /**
     * The predefined types of mistakes.
     *
     * @var array<string>
     */
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

    /**
     * Get the student who committed the violation.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /**
     * Get the user who reported the violation.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function reporter()
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    /**
     * Scope a query to only include violations of a specific type.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $type
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByType($query, $type)
    {
        return $query->where('mistake_type', $type);
    }
}
