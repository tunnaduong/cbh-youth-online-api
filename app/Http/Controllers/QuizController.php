<?php

namespace App\Http\Controllers;

use App\Models\AuthAccount;
use App\Models\QuizQuestion;
use App\Models\QuizSet;
use App\Models\QuizSetPlay;
use App\Models\StudyMaterialCategory;
use App\Services\PointsService;
use App\Services\QuizGenerationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class QuizController extends Controller
{
  private const MAX_COUNT = 50;
  private const OTHER_TOPIC = 'Khác';
  private const RANDOM_TOPIC = 'Ngẫu nhiên';
  private const GRADES = ['10', '11', '12'];

  // Points awarded per correct answer, scaled by difficulty - harder
  // question sets are worth more, same idea as the game XP ranking.
  private const DIFFICULTY_POINTS = [
    'easy' => 1,
    'medium' => 2,
    'hard' => 3,
  ];

  /**
   * Topics the user can pick from in the setup UI - the same subjects used
   * by the study materials marketplace, plus "Khác" (handled client-side)
   * for a free-text topic.
   */
  public function topics()
  {
    $topics = StudyMaterialCategory::orderBy('order')
      ->pluck('name')
      ->reject(fn($name) => $name === self::OTHER_TOPIC)
      ->values();

    return response()->json([
      'random' => self::RANDOM_TOPIC,
      'topics' => $topics,
      'other' => self::OTHER_TOPIC,
    ]);
  }

  /**
   * Start a quiz. Questions always come fresh from the AI for the chosen
   * topic/grade/difficulty. Every AI-generated question for a predefined
   * topic (not the free-text "Khác") is also saved into the local bank so
   * it's available later. If the AI call fails, we fall back to serving
   * random questions straight from that local bank - regardless of the
   * topic/grade the user picked - so the quiz can still start. Never
   * returns answers/explanations - see submit()/answer().
   */
  public function start(Request $request)
  {
    $request->validate([
      'count' => 'required|integer|min:1|max:' . self::MAX_COUNT,
      'difficulty' => 'required|string|in:easy,medium,hard',
      'grade' => 'required|string|in:' . implode(',', self::GRADES),
      'topic' => 'required|string|max:100',
      'custom_topic' => 'required_if:topic,' . self::OTHER_TOPIC . '|nullable|string|max:100',
    ]);

    $user = Auth::user();
    $count = (int) $request->count;
    $difficulty = $request->difficulty;
    $grade = $request->grade;
    $isCustomTopic = $request->topic === self::OTHER_TOPIC;
    $isRandomTopic = $request->topic === self::RANDOM_TOPIC;
    // null topic tells QuizGenerationService to let the AI pick its own -
    // the actual topic it lands on comes back in $generated['topic'].
    $topicLabel = $isCustomTopic ? trim($request->custom_topic) : ($isRandomTopic ? null : $request->topic);

    $bankIdsToMarkSeen = collect();
    $topicOut = $topicLabel;
    $gradeOut = $grade;

    try {
      $generated = app(QuizGenerationService::class)->generate($count, $difficulty, $topicLabel, $grade, $isCustomTopic);
      $resolvedTopic = $isCustomTopic ? $topicLabel : $generated['topic'];
      $topicOut = $resolvedTopic;

      $questionsPayload = collect($generated['questions'])->map(function ($q) use ($isCustomTopic, $resolvedTopic, $grade, $difficulty, &$bankIdsToMarkSeen) {
        $bankId = null;
        if (!$isCustomTopic) {
          $bankId = QuizQuestion::create([
            'topic' => $resolvedTopic,
            'grade' => $grade,
            'difficulty' => $difficulty,
            'question' => $q['question'],
            'options' => $q['options'],
            'answer' => $q['answer'],
            'explanation' => $q['explanation'] ?? '',
          ])->id;
          $bankIdsToMarkSeen->push($bankId);
        }

        return [
          'question' => $q['question'],
          'options' => $q['options'],
          'answer' => $q['answer'],
          'explanation' => $q['explanation'] ?? '',
        ];
      });
    } catch (\Throwable $e) {
      // AI unavailable - fall back to whatever is in the local bank,
      // ignoring the requested topic/grade/difficulty entirely so the
      // quiz can still start with whatever variety is on hand.
      $seenQuestionIds = DB::table('cyo_quiz_question_seen')
        ->where('user_id', $user->id)
        ->pluck('quiz_question_id');

      $bankQuestions = QuizQuestion::whereNotIn('id', $seenQuestionIds)
        ->inRandomOrder()
        ->limit($count)
        ->get();

      $stillNeeded = $count - $bankQuestions->count();
      if ($stillNeeded > 0) {
        // Bank doesn't have enough unseen questions left - top up with
        // repeats rather than fail outright.
        $extra = QuizQuestion::whereNotIn('id', $bankQuestions->pluck('id'))
          ->inRandomOrder()
          ->limit($stillNeeded)
          ->get();
        $bankQuestions = $bankQuestions->concat($extra);
      }

      if ($bankQuestions->isEmpty()) {
        return response()->json([
          'message' => 'Không thể tạo câu hỏi lúc này, vui lòng thử lại sau.',
        ], 503);
      }

      $bankIdsToMarkSeen = $bankQuestions->pluck('id');
      $topics = $bankQuestions->pluck('topic')->unique();
      $topicOut = $topics->count() === 1 ? $topics->first() : 'Tổng hợp nhiều chủ đề';
      $grades = $bankQuestions->pluck('grade')->filter()->unique();
      $gradeOut = $grades->count() === 1 ? $grades->first() : null;

      $questionsPayload = $bankQuestions->map(fn($q) => [
        'question' => $q->question,
        'options' => $q->options,
        'answer' => $q->answer,
        'explanation' => $q->explanation,
      ])->values();
    }

    $questionsPayload = $questionsPayload->values()->map(fn($q, $i) => array_merge(['id' => $i + 1], $q));

    $quizSet = DB::transaction(function () use ($questionsPayload, $difficulty, $topicOut, $gradeOut, $user, $bankIdsToMarkSeen) {
      $set = QuizSet::create([
        'topic' => $topicOut,
        'grade' => $gradeOut,
        'difficulty' => $difficulty,
        'question_count' => $questionsPayload->count(),
        'questions' => $questionsPayload,
        'served_count' => 1,
      ]);
      QuizSetPlay::create(['quiz_set_id' => $set->id, 'user_id' => $user->id]);

      if ($bankIdsToMarkSeen->isNotEmpty()) {
        $now = now();
        $seenRows = $bankIdsToMarkSeen->map(fn($id) => [
          'user_id' => $user->id,
          'quiz_question_id' => $id,
          'created_at' => $now,
        ])->all();
        DB::table('cyo_quiz_question_seen')->insertOrIgnore($seenRows);
      }

      return $set;
    });

    return response()->json([
      'quiz_set_id' => $quizSet->id,
      'topic' => $quizSet->topic,
      'grade' => $quizSet->grade,
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
