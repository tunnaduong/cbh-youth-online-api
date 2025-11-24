<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Represents a pending deposit request.
 *
 * @property int $id
 * @property int $user_id
 * @property string $deposit_code
 * @property int $amount_vnd
 * @property int $expected_points
 * @property string $status pending|completed|expired
 * @property \Illuminate\Support\Carbon $expires_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class PendingDeposit extends Model
{
  use HasFactory;

  /**
   * The table associated with the model.
   *
   * @var string
   */
  protected $table = 'cyo_pending_deposits';

  /**
   * The attributes that are mass assignable.
   *
   * @var array<int, string>
   */
  protected $fillable = [
    'user_id',
    'deposit_code',
    'amount_vnd',
    'expected_points',
    'status',
    'expires_at',
  ];

  /**
   * The attributes that should be cast.
   *
   * @var array<string, string>
   */
  protected $casts = [
    'amount_vnd' => 'integer',
    'expected_points' => 'integer',
    'expires_at' => 'datetime',
  ];

  /**
   * Get the user who created this deposit request.
   *
   * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
   */
  public function user()
  {
    return $this->belongsTo(AuthAccount::class, 'user_id');
  }

  /**
   * Check if the deposit code is expired.
   *
   * @return bool
   */
  public function isExpired()
  {
    return $this->expires_at->isPast();
  }
}

