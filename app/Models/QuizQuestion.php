<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A single question in the reusable question bank. Every AI-generated
 * question ends up here (see QuizGenerationService via QuizController::
 * pickQuestions) so later quizzes can serve it "offline" (no AI call)
 * instead of always generating fresh - see QuizController::OFFLINE_RATIO.
 *
 * @property int $id
 * @property string $topic
 * @property string $difficulty easy|medium|hard
 * @property string $question
 * @property array $options [4]
 * @property string $answer A|B|C|D
 * @property string|null $explanation
 */
class QuizQuestion extends Model
{
  protected $table = 'cyo_quiz_questions';

  protected $fillable = [
    'topic',
    'grade',
    'difficulty',
    'question',
    'options',
    'answer',
    'explanation',
  ];

  protected $casts = [
    'options' => 'array',
  ];
}
