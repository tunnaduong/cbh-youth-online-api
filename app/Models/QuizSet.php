<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A batch of quiz questions - either AI-generated from a topic/grade
 * (creator_id null, see QuizGenerationService) or parsed from a creator's
 * own uploaded content (is_custom true, see CustomQuizParsingService).
 * Shared across users (each user only ever sees a given set once - see
 * QuizSetPlay).
 *
 * @property int $id
 * @property int|null $creator_id
 * @property bool $is_custom
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
    'creator_id',
    'is_custom',
    'topic',
    'grade',
    'difficulty',
    'question_count',
    'questions',
    'served_count',
  ];

  protected $casts = [
    'questions' => 'array',
    'is_custom' => 'boolean',
  ];

  public function plays(): HasMany
  {
    return $this->hasMany(QuizSetPlay::class);
  }

  public function creator(): BelongsTo
  {
    return $this->belongsTo(AuthAccount::class, 'creator_id');
  }
}
