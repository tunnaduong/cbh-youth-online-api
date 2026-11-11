<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PrintRequest extends Model
{
  use HasFactory;

  protected $table = 'cyo_print_requests';

  protected $fillable = [
    'user_id',
    'title',
    'description',
    'file_id',
    'pages_count',
    'delivery_method',
    'class_or_location',
    'notes',
    'status',
    'assigned_staff_id',
    'approved_at',
    'printing_started_at',
    'ready_at',
    'delivered_at',
    'picked_up_at',
    'rejection_reason',
  ];

  protected $casts = [
    'pages_count' => 'integer',
    'approved_at' => 'datetime',
    'printing_started_at' => 'datetime',
    'ready_at' => 'datetime',
    'delivered_at' => 'datetime',
    'picked_up_at' => 'datetime',
  ];

  public function user()
  {
    return $this->belongsTo(AuthAccount::class, 'user_id');
  }

  public function assignedStaff()
  {
    return $this->belongsTo(AuthAccount::class, 'assigned_staff_id');
  }

  public function file()
  {
    return $this->belongsTo(UserContent::class, 'file_id');
  }
}
