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
 * @property array|null $answers  {questionId: "A"} - filled in one at a time
 *   as the user answers each question, see QuizController::answer()
 * @property int $points
 * @property \Illuminate\Support\Carbon|null $submitted_at
 * @property \Illuminate\Support\Carbon|null $points_awarded_at  Set once, the
 *   first time this user earns points for this quiz set, and never cleared
 *   again - even across a restart() - so a replay never pays out twice.
 */
class QuizSetPlay extends Model
{
  protected $table = 'cyo_quiz_set_plays';

  protected $fillable = [
    'quiz_set_id',
    'user_id',
    'score',
    'answers',
    'points',
    'submitted_at',
    'points_awarded_at',
  ];

  protected $casts = [
    'answers' => 'array',
    'submitted_at' => 'datetime',
    'points_awarded_at' => 'datetime',
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
