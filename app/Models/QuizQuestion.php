<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A question in the old reusable question bank. No longer written or read:
 * quizzes are online only and always generated fresh by the AI (see
 * QuizController::start). Kept with its table so existing rows can still be
 * removed with `php artisan quiz:clear-cached`.
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
