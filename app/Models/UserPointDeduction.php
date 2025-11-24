<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use App\Services\PointsService;

/**
 * Represents a user point deduction record.
 *
 * @property int $id
 * @property int $user_id
 * @property int $points_deducted
 * @property string $reason
 * @property string|null $description
 * @property int $admin_id
 * @property bool $is_active
 * @property \Carbon\Carbon|null $expires_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read \App\Models\AuthAccount $user
 * @property-read \App\Models\AuthAccount $admin
 */
class UserPointDeduction extends Model
{
  use HasFactory;

  /**
   * The table associated with the model.
   *
   * @var string
   */
  protected $table = 'cyo_user_point_deductions';

  /**
   * The attributes that are mass assignable.
   *
   * @var array<int, string>
   */
  protected $fillable = [
    'user_id',
    'points_deducted',
    'reason',
    'description',
    'admin_id',
    'is_active',
    'expires_at'
  ];

  /**
   * The attributes that should be cast.
   *
   * @var array<string, string>
   */
  protected $casts = [
    'is_active' => 'boolean',
    'expires_at' => 'datetime',
    'points_deducted' => 'integer'
  ];

  /**
   * Predefined reasons for point deductions.
   *
   * @var array<string>
   */
  const DEDUCTION_REASONS = [
    'Spam Content',
    'Inappropriate Behavior',
    'Violation of Community Guidelines',
    'Harassment',
    'Fake Information',
    'Multiple Account Abuse',
    'System Abuse',
    'Other'
  ];

  /**
   * Get the user who received the deduction.
   *
   * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
   */
  public function user()
  {
    return $this->belongsTo(AuthAccount::class, 'user_id');
  }

  /**
   * Get the admin who applied the deduction.
   *
   * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
   */
  public function admin()
  {
    return $this->belongsTo(AuthAccount::class, 'admin_id');
  }

  /**
   * Scope a query to only include active deductions.
   *
   * @param  \Illuminate\Database\Eloquent\Builder  $query
   * @return \Illuminate\Database\Eloquent\Builder
   */
  public function scopeActive($query)
  {
    return $query->where('is_active', true)
      ->where(function ($q) {
        $q->whereNull('expires_at')
          ->orWhere('expires_at', '>', now());
      });
  }

  /**
   * Scope a query to only include deductions for a specific user.
   *
   * @param  \Illuminate\Database\Eloquent\Builder  $query
   * @param  int  $userId
   * @return \Illuminate\Database\Eloquent\Builder
   */
  public function scopeForUser($query, $userId)
  {
    return $query->where('user_id', $userId);
  }

  /**
   * Check if the deduction is expired.
   *
   * @return bool
   */
  public function isExpired()
  {
    return $this->expires_at && $this->expires_at->isPast();
  }

  /**
   * Get the total active deductions for a user.
   *
   * @param  int  $userId
   * @return int
   */
  public static function getTotalActiveDeductions($userId)
  {
    return self::active()
      ->forUser($userId)
      ->sum('points_deducted');
  }

  /**
   * The "booted" method of the model.
   *
   * @return void
   */
  protected static function boot()
  {
    parent::boot();

    // Deduct points when a deduction is created
    static::created(function ($deduction) {
      if ($deduction->is_active && !$deduction->isExpired()) {
        PointsService::onPointDeduction($deduction->user_id, $deduction->points_deducted);
      }
    });

    // Handle deduction updates (e.g., activate/deactivate, points change)
    static::updated(function ($deduction) {
      $wasActive = $deduction->getOriginal('is_active') && !$deduction->isExpired();
      $isActive = $deduction->is_active && !$deduction->isExpired();
      $pointsChanged = $deduction->getOriginal('points_deducted') != $deduction->points_deducted;
      $oldPoints = $deduction->getOriginal('points_deducted');
      
      if ($pointsChanged || ($wasActive != $isActive)) {
        // If was active, add back the old amount
        if ($wasActive) {
          PointsService::addPoints(
            $deduction->user_id,
            $oldPoints,
            'deduction_reversal',
            'Reversal of point deduction'
          );
        }
        // If now active, deduct the new amount
        if ($isActive) {
          PointsService::onPointDeduction($deduction->user_id, $deduction->points_deducted);
        }
      }
    });

    // Restore points when a deduction is deleted (if it was active)
    static::deleted(function ($deduction) {
      if ($deduction->is_active && !$deduction->isExpired()) {
        PointsService::addPoints(
          $deduction->user_id,
          $deduction->points_deducted,
          'deduction_reversal',
          'Point deduction removed'
        );
      }
    });
  }
}
