<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A batch of AI-generated quiz questions. Shared across users (each user
 * only ever sees a given set once - see QuizSetPlay), retired from the
 * reuse pool once served_count crosses QuizGenerationService::MAX_SERVES.
 *
 * @property int $id
 * @property string $topic
 * @property string $difficulty easy|medium|hard
 * @property int $question_count
 * @property array $questions [{id, question, options[4], answer, explanation}]
 * @property int $served_count
 */
class QuizSet extends Model
{
  protected $table = 'cyo_quiz_sets';

  protected $fillable = [
    'topic',
    'difficulty',
    'question_count',
    'questions',
    'served_count',
  ];

  protected $casts = [
    'questions' => 'array',
  ];

  public function plays(): HasMany
  {
    return $this->hasMany(QuizSetPlay::class);
  }
}
