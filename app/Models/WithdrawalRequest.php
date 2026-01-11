<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Represents a withdrawal request.
 *
 * @property int $id
 * @property int $user_id
 * @property int $amount Points to withdraw
 * @property string $bank_account
 * @property string $bank_name
 * @property string $account_holder
 * @property string $status pending|approved|rejected|completed|cancelled
 * @property int|null $admin_id
 * @property string|null $admin_note
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class WithdrawalRequest extends Model
{
  use HasFactory;

  /**
   * The table associated with the model.
   *
   * @var string
   */
  protected $table = 'cyo_withdrawal_requests';

  /**
   * The attributes that are mass assignable.
   *
   * @var array<int, string>
   */
  protected $fillable = [
    'user_id',
    'amount',
    'bank_account',
    'bank_name',
    'account_holder',
    'status',
    'admin_id',
    'admin_note',
  ];

  /**
   * The attributes that should be cast.
   *
   * @var array<string, string>
   */
  protected $casts = [
    'amount' => 'integer',
  ];

  /**
   * Get the user who made the withdrawal request.
   *
   * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
   */
  public function user()
  {
    return $this->belongsTo(AuthAccount::class, 'user_id');
  }

  /**
   * Get the admin who processed the request.
   *
   * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
   */
  public function admin()
  {
    return $this->belongsTo(AuthAccount::class, 'admin_id');
  }
}
