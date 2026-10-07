<?php

namespace App\Http\Controllers;

use App\Models\AuthAccount;
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

  // Site-wide points for finishing a question set, for a set of 10
  // questions. A shorter or longer set is worth proportionally less or more
  // (see globalPointsFor()). Not tied to the score: this rewards doing the
  // set; the quiz ranking above is what rewards right answers.
  private const GLOBAL_POINTS_PER_10_QUESTIONS = [
    'easy' => 2,
    'medium' => 4,
    'hard' => 6,
  ];

  /**
   * Site-wide points for completing this set: the difficulty's rate for 10
   * questions, scaled by how many questions the set has (5 easy questions
   * = 1 point, 20 hard ones = 12). At least 1.
   */
  private function globalPointsFor($quizSet): int
  {
    $perTen = self::GLOBAL_POINTS_PER_10_QUESTIONS[$quizSet->difficulty]
      ?? self::GLOBAL_POINTS_PER_10_QUESTIONS['easy'];
    $questions = (int) ($quizSet->question_count ?: count($quizSet->questions ?? []));

    return max(1, (int) round($perTen * $questions / 10));
  }

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
   * topic/grade/difficulty - quizzes are online only. Generated questions
   * are not kept in the question bank (QuizQuestion / cyo_quiz_questions)
   * any more, and there is no fallback to it: if the AI call fails, the
   * quiz can't start (503). Never returns answers/explanations - see
   * submit()/answer().
   */
  public function start(Request $request)
  {
    $request->validate([
      'count' => 'required|integer|min:1|max:' . self::MAX_COUNT,
      'difficulty' => 'required|string|in:easy,medium,hard',
      'grade' => 'required|string|in:' . implode(',', self::GRADES),
      'topic' => 'required|string|max:100',
      'custom_topic' => 'required_if:topic,' . self::OTHER_TOPIC . '|nullable|string|max:256',
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

    try {
      $generated = app(QuizGenerationService::class)->generate($count, $difficulty, $topicLabel, $grade, $isCustomTopic);
    } catch (\Throwable $e) {
      return response()->json([
        'message' => 'Không thể tạo câu hỏi lúc này, vui lòng thử lại sau.',
      ], 503);
    }

    $topicOut = $isCustomTopic ? $topicLabel : $generated['topic'];
    $gradeOut = $grade;

    $questionsPayload = collect($generated['questions'])->map(fn($q) => [
      'question' => $q['question'],
      'options' => $q['options'],
      'answer' => $q['answer'],
      'explanation' => $q['explanation'] ?? '',
    ]);

    $questionsPayload = $questionsPayload->values()->map(fn($q, $i) => array_merge(['id' => $i + 1], $q));

    $quizSet = DB::transaction(function () use ($questionsPayload, $difficulty, $topicOut, $gradeOut, $user) {
      $set = QuizSet::create([
        'topic' => $topicOut,
        'grade' => $gradeOut,
        'difficulty' => $difficulty,
        'question_count' => $questionsPayload->count(),
        'questions' => $questionsPayload,
        'served_count' => 1,
      ]);
      QuizSetPlay::create(['quiz_set_id' => $set->id, 'user_id' => $user->id]);

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
   * Public preview of a shared quiz set (topic/difficulty/question count and
   * who originally shared it) - shown before the viewer logs in or joins,
   * so a share link is useful even to a signed-out visitor.
   */
  public function preview($quizSetId)
  {
    $quizSet = QuizSet::findOrFail($quizSetId);

    // A custom quiz's real "shared by" is always its creator, whether or
    // not they've actually played it themselves yet - unlike an
    // AI-generated set, where "shared by" is whoever happened to start it
    // first (there is no creator concept there).
    if ($quizSet->is_custom) {
      $creator = $quizSet->creator()->with('profile')->first();
      $sharedBy = $creator ? [
        'username' => $creator->username,
        'profile_name' => $creator->profile->profile_name ?? $creator->username,
      ] : null;
    } else {
      $firstPlay = QuizSetPlay::where('quiz_set_id', $quizSetId)
        ->with('user.profile')
        ->oldest()
        ->first();
      $sharedBy = $firstPlay?->user ? [
        'username' => $firstPlay->user->username,
        'profile_name' => $firstPlay->user->profile->profile_name ?? $firstPlay->user->username,
      ] : null;
    }

    return response()->json([
      'quiz_set_id' => $quizSet->id,
      'topic' => $quizSet->topic,
      'grade' => $quizSet->grade,
      'difficulty' => $quizSet->difficulty,
      'question_count' => $quizSet->question_count,
      'is_custom' => $quizSet->is_custom,
      'shared_by' => $sharedBy,
    ]);
  }

  /**
   * Replay a quiz set (shared or own custom set) the user has already
   * played before - any number of times. Resets this user's existing play
   * back to "in progress" so they can answer fresh, but deliberately leaves
   * points_awarded_at untouched: whether this user has ever earned points
   * for this exact quiz set is tracked independently of the current
   * attempt, so a replay's answer()/submit() never pays out a second time
   * (see the points_awarded_at check there). Requires an existing play
   * (i.e. join()/start() already ran once) - there's nothing to restart
   * otherwise.
   */
  public function restart($quizSetId)
  {
    $user = Auth::user();
    $quizSet = QuizSet::findOrFail($quizSetId);

    $play = QuizSetPlay::where('quiz_set_id', $quizSet->id)
      ->where('user_id', $user->id)
      ->firstOrFail();

    $play->update([
      'answers' => null,
      'score' => null,
      'points' => 0,
      'submitted_at' => null,
    ]);

    $questionsById = collect($quizSet->questions)->keyBy('id');

    return response()->json([
      'quiz_set_id' => $quizSet->id,
      'topic' => $quizSet->topic,
      'grade' => $quizSet->grade,
      'difficulty' => $quizSet->difficulty,
      'question_count' => $quizSet->question_count,
      'status' => 'in_progress',
      'answered' => [],
      'questions' => $questionsById->map(fn($q) => [
        'id' => $q['id'],
        'question' => $q['question'],
        'options' => $q['options'],
      ])->values(),
    ]);
  }

  /**
   * Join a quiz set someone else already started, so both people answer the
   * exact same questions - the whole point of sharing a quiz. Reuses the
   * existing QuizSetPlay unique(quiz_set_id, user_id) constraint: joining
   * twice just resumes/re-reads the same play instead of erroring.
   */
  public function join(Request $request, $quizSetId)
  {
    $user = Auth::user();
    $quizSet = QuizSet::findOrFail($quizSetId);

    $play = QuizSetPlay::firstOrCreate(
      ['quiz_set_id' => $quizSet->id, 'user_id' => $user->id],
      []
    );

    if ($play->wasRecentlyCreated) {
      $quizSet->increment('served_count');
    }

    $questionsById = collect($quizSet->questions)->keyBy('id');

    if ($play->submitted_at) {
      return response()->json([
        'quiz_set_id' => $quizSet->id,
        'topic' => $quizSet->topic,
        'grade' => $quizSet->grade,
        'difficulty' => $quizSet->difficulty,
        'question_count' => $quizSet->question_count,
        'status' => 'completed',
        'questions' => $questionsById->map(fn($q) => [
          'id' => $q['id'],
          'question' => $q['question'],
          'options' => $q['options'],
        ])->values(),
        'result' => [
          'score' => $play->score,
          'total' => $quizSet->question_count,
          'points' => $play->points,
          'results' => $this->buildResults($questionsById, collect($play->answers ?? [])->map(fn($answer, $id) => ['id' => $id, 'answer' => $answer])),
        ],
      ]);
    }

    return response()->json([
      'quiz_set_id' => $quizSet->id,
      'topic' => $quizSet->topic,
      'grade' => $quizSet->grade,
      'difficulty' => $quizSet->difficulty,
      'question_count' => $quizSet->question_count,
      'status' => 'in_progress',
      'answered' => $play->answers ?? [],
      'questions' => $questionsById->map(fn($q) => [
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

    $isCreatorPlayingOwnQuiz = $this->isCreatorPlayingOwnQuiz($quizSet, $user);
    // A restart() resets score/submitted_at for a fresh attempt but leaves
    // points_awarded_at alone - so a replay (this user's 2nd+ completion of
    // this exact quiz set) is worth 0 points here even though it's still
    // scored/shown normally, same as a creator playing their own quiz.
    $alreadyAwarded = $play->points_awarded_at !== null;
    $pointsPerAnswer = self::DIFFICULTY_POINTS[$quizSet->difficulty] ?? 1;
    $points = ($isCreatorPlayingOwnQuiz || $alreadyAwarded) ? 0 : $score * $pointsPerAnswer;

    $play->update([
      'score' => $score,
      'points' => $points,
      'submitted_at' => now(),
      'points_awarded_at' => $alreadyAwarded ? $play->points_awarded_at : ($isCreatorPlayingOwnQuiz ? null : now()),
    ]);

    if (!$isCreatorPlayingOwnQuiz && !$alreadyAwarded) {
      $globalPoints = $this->globalPointsFor($quizSet);
      PointsService::onQuizCompleted($user->id, $globalPoints, $play->id);
    }

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
      $isCreatorPlayingOwnQuiz = $this->isCreatorPlayingOwnQuiz($quizSet, $user);
      // See the same check in submit() - a restart() attempt (this user's
      // 2nd+ completion of this exact quiz set) never earns points again.
      $alreadyAwarded = $play->points_awarded_at !== null;
      $pointsPerAnswer = self::DIFFICULTY_POINTS[$quizSet->difficulty] ?? 1;
      $points = ($isCreatorPlayingOwnQuiz || $alreadyAwarded) ? 0 : $score * $pointsPerAnswer;

      $play->score = $score;
      $play->points = $points;
      $play->submitted_at = now();
      if (!$alreadyAwarded && !$isCreatorPlayingOwnQuiz) {
        $play->points_awarded_at = now();
      }
      $play->save();

      if (!$isCreatorPlayingOwnQuiz && !$alreadyAwarded) {
        $globalPoints = $this->globalPointsFor($quizSet);
        PointsService::onQuizCompleted($user->id, $globalPoints, $play->id);
      }

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
          'avatar_url' => $user->avatarUrl(),
          'points' => (int) $row->total_points,
        ];
      })
      ->values();

    return response()->json(['leaderboard' => $leaderboard]);
  }

  /**
   * A custom quiz's creator gets no points/XP for completing their own
   * quiz - both the quiz-leaderboard-facing $play->points and the global
   * PointsService currency - so they can't farm either pool by writing a
   * quiz and immediately "winning" it themselves.
   */
  private function isCreatorPlayingOwnQuiz(QuizSet $quizSet, AuthAccount $user): bool
  {
    return $quizSet->is_custom && $quizSet->creator_id !== null && $quizSet->creator_id === $user->id;
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
