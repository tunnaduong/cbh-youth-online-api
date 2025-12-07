<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Represents a points transaction (deposit, withdrawal, purchase, earning, etc.).
 *
 * @property int $id
 * @property int $user_id
 * @property string $type deposit|withdrawal|purchase|earning|post|vote|comment
 * @property int $amount Points (can be positive or negative)
 * @property string|null $sepay_transaction_id
 * @property string|null $reference_code
 * @property string $status pending|completed|failed
 * @property string|null $description
 * @property int|null $related_id ID of related entity (post/vote/comment/purchase)
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class PointsTransaction extends Model
{
  use HasFactory;

  /**
   * The table associated with the model.
   *
   * @var string
   */
  protected $table = 'cyo_points_transactions';

  /**
   * The attributes that are mass assignable.
   *
   * @var array<int, string>
   */
  protected $fillable = [
    'user_id',
    'type',
    'amount',
    'sepay_transaction_id',
    'reference_code',
    'status',
    'description',
    'related_id',
  ];

  /**
   * The attributes that should be cast.
   *
   * @var array<string, string>
   */
  protected $casts = [
    'amount' => 'integer',
    'related_id' => 'integer',
  ];

  /**
   * Get the user who owns this transaction.
   *
   * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
   */
  public function user()
  {
    return $this->belongsTo(AuthAccount::class, 'user_id');
  }
}


