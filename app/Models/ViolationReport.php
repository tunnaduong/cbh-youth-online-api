<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A student or class violation reported from the app's "Báo cáo vi phạm"
 * flow, handled by admins in the panel (see ViolationReportController).
 *
 * @property int $id
 * @property int $reporter_id
 * @property string $type student|class
 * @property string $subject_name the student's or the class's name
 * @property string|null $violation_type
 * @property \Illuminate\Support\Carbon|null $report_date
 * @property string|null $notes
 * @property int|null $absences
 * @property bool|null $cleanliness
 * @property bool|null $uniform
 * @property string $status pending|reviewed|resolved|dismissed
 * @property string|null $admin_notes
 * @property int|null $reviewed_by
 * @property \Illuminate\Support\Carbon|null $reviewed_at
 */
class ViolationReport extends Model
{
  public const TYPES = ['student', 'class'];
  public const STATUSES = ['pending', 'reviewed', 'resolved', 'dismissed'];

  protected $table = 'cyo_violation_reports';

  protected $fillable = [
    'reporter_id',
    'type',
    'subject_name',
    'violation_type',
    'report_date',
    'notes',
    'absences',
    'cleanliness',
    'uniform',
    'status',
    'admin_notes',
    'reviewed_by',
    'reviewed_at',
  ];

  protected $casts = [
    'report_date' => 'date:Y-m-d',
    'cleanliness' => 'boolean',
    'uniform' => 'boolean',
    'absences' => 'integer',
    'reviewed_at' => 'datetime',
  ];

  public function reporter()
  {
    return $this->belongsTo(AuthAccount::class, 'reporter_id');
  }

  public function reviewedBy()
  {
    return $this->belongsTo(AuthAccount::class, 'reviewed_by');
  }
}
