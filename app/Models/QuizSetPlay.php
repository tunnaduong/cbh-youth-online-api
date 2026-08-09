<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Marks that a user has been served a given QuizSet - prevents that exact
 * set from ever being handed to them again (a different user can still get
 * it, up to QuizGenerationService::MAX_SERVES total).
 *
 * @property int $id
 * @property int $quiz_set_id
 * @property int $user_id
 * @property int|null $score
 * @property \Illuminate\Support\Carbon|null $submitted_at
 */
class QuizSetPlay extends Model
{
  protected $table = 'cyo_quiz_set_plays';

  protected $fillable = [
    'quiz_set_id',
    'user_id',
    'score',
    'submitted_at',
  ];

  protected $casts = [
    'submitted_at' => 'datetime',
  ];

  public function quizSet(): BelongsTo
  {
    return $this->belongsTo(QuizSet::class);
  }

  public function user(): BelongsTo
  {
    return $this->belongsTo(AuthAccount::class, 'user_id');
  }
}
