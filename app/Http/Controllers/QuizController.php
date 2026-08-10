<?php

namespace App\Http\Controllers;

use App\Models\AuthAccount;
use App\Models\QuizQuestion;
use App\Models\QuizSet;
use App\Models\QuizSetPlay;
use App\Services\PointsService;
use App\Services\QuizGenerationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class QuizController extends Controller
{
  private const ALLOWED_COUNTS = [5, 10, 20, 50, 100];
  private const MAX_CUSTOM_COUNT = 100;

  // Points awarded per correct answer, scaled by difficulty - harder
  // question sets are worth more, same idea as the game XP ranking.
  private const DIFFICULTY_POINTS = [
    'easy' => 1,
    'medium' => 2,
    'hard' => 3,
  ];

  // Below this many banked questions for a difficulty, we still lean on AI
  // for half of every quiz so the bank fills up fast. Once it's past this
  // size, most of the quiz is served from the bank (cheap, instant) and
  // only a quarter comes from a fresh AI call - keeps the bank growing
  // slowly forever while cutting AI usage once there's enough variety.
  private const BANK_MATURITY_THRESHOLD = 1000;
  private const OFFLINE_RATIO_SMALL_BANK = 0.5;
  private const OFFLINE_RATIO_MATURE_BANK = 0.75;

  /**
   * Start a quiz: composed per-question, not per-set. Some questions are
   * drawn straight from the bank (previously generated, never before shown
   * to this user); the rest are generated fresh via AI right now and added
   * to the bank for future quizzes to draw from. The offline/online split
   * depends on how big the bank already is for this difficulty - see
   * BANK_MATURITY_THRESHOLD. Never returns answers/explanations - see
   * submit().
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

    $seenQuestionIds = DB::table('cyo_quiz_question_seen')
      ->where('user_id', $user->id)
      ->pluck('quiz_question_id');

    $bankSize = QuizQuestion::where('difficulty', $difficulty)->count();
    $offlineRatio = $bankSize < self::BANK_MATURITY_THRESHOLD
      ? self::OFFLINE_RATIO_SMALL_BANK
      : self::OFFLINE_RATIO_MATURE_BANK;
    $offlineTarget = (int) round($count * $offlineRatio);

    $offlineQuestions = QuizQuestion::where('difficulty', $difficulty)
      ->whereNotIn('id', $seenQuestionIds)
      ->inRandomOrder()
      ->limit($offlineTarget)
      ->get();

    // Whatever the bank couldn't cover (either short on the ratio, or the
    // bank itself is too small/exhausted for this user) gets made up by AI.
    $onlineNeeded = $count - $offlineQuestions->count();

    $generatedQuestions = collect();
    if ($onlineNeeded > 0) {
      try {
        $generated = app(QuizGenerationService::class)->generate($onlineNeeded, $difficulty);
      } catch (\Throwable $e) {
        // If AI is unavailable but the bank alone already covers the full
        // count, just proceed offline-only instead of failing the request.
        if ($offlineQuestions->count() < $count) {
          return response()->json([
            'message' => 'Không thể tạo câu hỏi lúc này, vui lòng thử lại sau.',
          ], 503);
        }
        $generated = ['topic' => null, 'questions' => []];
      }

      $generatedQuestions = collect($generated['questions'])->map(function ($q) use ($generated, $difficulty) {
        return QuizQuestion::create([
          'topic' => $generated['topic'] ?: 'Kiến thức tổng hợp',
          'difficulty' => $difficulty,
          'question' => $q['question'],
          'options' => $q['options'],
          'answer' => $q['answer'],
          'explanation' => $q['explanation'] ?? '',
        ]);
      });
    }

    $bankQuestions = $offlineQuestions->concat($generatedQuestions)->shuffle()->values();

    $topics = $bankQuestions->pluck('topic')->unique();
    $topicLabel = $topics->count() === 1 ? $topics->first() : 'Tổng hợp nhiều chủ đề';

    $questionsPayload = $bankQuestions->values()->map(fn($q, $i) => [
      'id' => $i + 1,
      'question' => $q->question,
      'options' => $q->options,
      'answer' => $q->answer,
      'explanation' => $q->explanation,
      // Kept only server-side to mark this exact question as seen and to
      // grade the answer - stripped before the response goes out below.
      '_bank_id' => $q->id,
    ]);

    $quizSet = DB::transaction(function () use ($questionsPayload, $count, $difficulty, $topicLabel, $user) {
      $set = QuizSet::create([
        'topic' => $topicLabel,
        'difficulty' => $difficulty,
        'question_count' => $questionsPayload->count(),
        'questions' => $questionsPayload->map(fn($q) => collect($q)->except('_bank_id'))->values(),
        'served_count' => 1,
      ]);
      QuizSetPlay::create(['quiz_set_id' => $set->id, 'user_id' => $user->id]);

      $bankIds = $questionsPayload->pluck('_bank_id');
      $now = now();
      $seenRows = $bankIds->map(fn($id) => [
        'user_id' => $user->id,
        'quiz_question_id' => $id,
        'created_at' => $now,
      ])->all();
      // ignore() - a question generated fresh above can't already be seen,
      // but stay defensive against any future concurrent-start race.
      DB::table('cyo_quiz_question_seen')->insertOrIgnore($seenRows);

      return $set;
    });

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
        'points' => $play->points,
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

    $pointsPerAnswer = self::DIFFICULTY_POINTS[$quizSet->difficulty] ?? 1;
    $points = $score * $pointsPerAnswer;

    $play->update(['score' => $score, 'points' => $points, 'submitted_at' => now()]);
    PointsService::onQuizCompleted($user->id, $points, $play->id);

    return response()->json([
      'score' => $score,
      'total' => $quizSet->question_count,
      'points' => $points,
      'results' => $this->buildResults($questionsById, $submittedById),
    ]);
  }

  /**
   * Grade a single question the instant the user answers it, revealing the
   * correct answer/explanation right away instead of waiting for the whole
   * quiz to be submitted. Once every question in the set has been answered
   * this way, the play is finalized (score/points computed, points
   * awarded) automatically - there's no separate "submit" step in this flow.
   */
  public function answer(Request $request, $quizSetId)
  {
    $request->validate([
      'id' => 'required|integer',
      'answer' => 'nullable|string|in:A,B,C,D',
    ]);

    $user = Auth::user();
    $play = QuizSetPlay::where('quiz_set_id', $quizSetId)
      ->where('user_id', $user->id)
      ->firstOrFail();

    if ($play->submitted_at) {
      return response()->json(['message' => 'Bài đố vui này đã hoàn thành.'], 409);
    }

    $quizSet = QuizSet::findOrFail($quizSetId);
    $question = collect($quizSet->questions)->firstWhere('id', $request->id);
    if (!$question) {
      return response()->json(['message' => 'Câu hỏi không hợp lệ.'], 422);
    }

    $answers = $play->answers ?? [];
    $answers[$request->id] = $request->answer;
    $play->answers = $answers;

    $answeredCount = count($answers);
    $finished = $answeredCount >= $quizSet->question_count;

    $result = [
      'id' => $question['id'],
      'is_correct' => $request->answer === $question['answer'],
      'correct_answer' => $question['answer'],
      'explanation' => $question['explanation'] ?? '',
      'answered_count' => $answeredCount,
      'total' => $quizSet->question_count,
      'finished' => $finished,
    ];

    if ($finished) {
      $score = 0;
      foreach (collect($quizSet->questions) as $q) {
        if (($answers[$q['id']] ?? null) === $q['answer']) {
          $score++;
        }
      }
      $pointsPerAnswer = self::DIFFICULTY_POINTS[$quizSet->difficulty] ?? 1;
      $points = $score * $pointsPerAnswer;

      $play->score = $score;
      $play->points = $points;
      $play->submitted_at = now();
      $play->save();
      PointsService::onQuizCompleted($user->id, $points, $play->id);

      $result['score'] = $score;
      $result['points'] = $points;
    } else {
      $play->save();
    }

    return response()->json($result);
  }

  /**
   * Quiz-specific leaderboard - total points earned from quiz plays only
   * (mirrors GameController::leaderboard).
   */
  public function leaderboard(Request $request)
  {
    $period = $request->input('period', 'week'); // week|all
    $query = DB::table('cyo_quiz_set_plays')
      ->whereNotNull('submitted_at')
      ->select('user_id')
      ->selectRaw('SUM(points) as total_points')
      ->groupBy('user_id')
      ->orderByDesc('total_points')
      ->limit(20);

    if ($period === 'week') {
      $query->where('submitted_at', '>=', now()->subWeek());
    }

    $rows = $query->get();

    $users = AuthAccount::whereIn('id', $rows->pluck('user_id'))
      ->with('profile')
      ->get()
      ->keyBy('id');

    $leaderboard = $rows
      ->filter(fn($row) => isset($users[$row->user_id]))
      ->map(function ($row) use ($users) {
        $user = $users[$row->user_id];
        return [
          'id' => $user->id,
          'username' => $user->username,
          'profile_name' => $user->profile->profile_name ?? $user->username,
          'avatar_url' => config('app.url') . "/v1.0/users/{$user->username}/avatar",
          'points' => (int) $row->total_points,
        ];
      })
      ->values();

    return response()->json(['leaderboard' => $leaderboard]);
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
