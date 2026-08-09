<?php

namespace App\Http\Controllers;

use App\Models\QuizSet;
use App\Models\QuizSetPlay;
use App\Services\QuizGenerationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class QuizController extends Controller
{
  // Once a generated set has been served to this many distinct users, it's
  // retired from the reuse pool and a fresh one gets generated instead -
  // keeps question sets from being reused indefinitely.
  private const MAX_SERVES = 12;

  private const ALLOWED_COUNTS = [5, 10, 20, 50, 100];
  private const MAX_CUSTOM_COUNT = 100;

  /**
   * Start a quiz: reuse an existing question set the user hasn't seen yet
   * (if one exists and isn't over-served), otherwise generate a brand new
   * one via AI. Never returns answers/explanations - see submit().
   */
  public function start(Request $request)
  {
    $request->validate([
      'count' => 'required|integer|min:1|max:' . self::MAX_CUSTOM_COUNT,
      'difficulty' => 'required|string|in:easy,medium,hard',
    ]);

    $user = Auth::user();
    $count = (int) $request->count;
    $difficulty = $request->difficulty;

    $quizSet = QuizSet::where('question_count', $count)
      ->where('difficulty', $difficulty)
      ->where('served_count', '<', self::MAX_SERVES)
      ->whereDoesntHave('plays', fn($q) => $q->where('user_id', $user->id))
      ->inRandomOrder()
      ->first();

    if ($quizSet) {
      DB::transaction(function () use ($quizSet, $user) {
        $quizSet->increment('served_count');
        QuizSetPlay::create(['quiz_set_id' => $quizSet->id, 'user_id' => $user->id]);
      });
    } else {
      try {
        $generated = app(QuizGenerationService::class)->generate($count, $difficulty);
      } catch (\Throwable $e) {
        return response()->json([
          'message' => 'Không thể tạo câu hỏi lúc này, vui lòng thử lại sau.',
        ], 503);
      }

      $quizSet = DB::transaction(function () use ($generated, $count, $difficulty, $user) {
        $set = QuizSet::create([
          'topic' => $generated['topic'],
          'difficulty' => $difficulty,
          'question_count' => $count,
          'questions' => $generated['questions'],
          'served_count' => 1,
        ]);
        QuizSetPlay::create(['quiz_set_id' => $set->id, 'user_id' => $user->id]);
        return $set;
      });
    }

    return response()->json([
      'quiz_set_id' => $quizSet->id,
      'topic' => $quizSet->topic,
      'difficulty' => $quizSet->difficulty,
      'question_count' => $quizSet->question_count,
      'questions' => collect($quizSet->questions)->map(fn($q) => [
        'id' => $q['id'],
        'question' => $q['question'],
        'options' => $q['options'],
      ])->values(),
    ]);
  }

  /**
   * Grade a submitted quiz attempt. Only the user who was served this exact
   * set (via start()) may submit for it; resubmitting just returns the
   * already-graded result instead of re-scoring.
   */
  public function submit(Request $request, $quizSetId)
  {
    $request->validate([
      'answers' => 'required|array|min:1',
      'answers.*.id' => 'required|integer',
      'answers.*.answer' => 'nullable|string',
    ]);

    $user = Auth::user();
    $play = QuizSetPlay::where('quiz_set_id', $quizSetId)
      ->where('user_id', $user->id)
      ->firstOrFail();

    $quizSet = QuizSet::findOrFail($quizSetId);
    $questionsById = collect($quizSet->questions)->keyBy('id');
    $submittedById = collect($request->answers)->keyBy('id');

    if ($play->submitted_at) {
      // Already graded - return the same result rather than re-scoring
      // (submitted answers aren't stored, so recomputing would need them
      // resent anyway; treat this as idempotent using what's stored).
      return response()->json([
        'score' => $play->score,
        'total' => $quizSet->question_count,
        'results' => $this->buildResults($questionsById, $submittedById),
      ]);
    }

    $score = 0;
    foreach ($questionsById as $id => $question) {
      $given = $submittedById->get($id)['answer'] ?? null;
      if ($given === $question['answer']) {
        $score++;
      }
    }

    $play->update(['score' => $score, 'submitted_at' => now()]);

    return response()->json([
      'score' => $score,
      'total' => $quizSet->question_count,
      'results' => $this->buildResults($questionsById, $submittedById),
    ]);
  }

  private function buildResults($questionsById, $submittedById)
  {
    return $questionsById->map(function ($question, $id) use ($submittedById) {
      $given = $submittedById->get($id)['answer'] ?? null;
      return [
        'id' => $id,
        'your_answer' => $given,
        'correct_answer' => $question['answer'],
        'is_correct' => $given === $question['answer'],
        'explanation' => $question['explanation'] ?? '',
      ];
    })->values();
  }
}
