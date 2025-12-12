<?php

namespace App\Http\Controllers;

use App\Models\AuthAccount;
use App\Models\Story;
use App\Models\Topic;
use App\Models\UserReport;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Handles the creation and management of user reports.
 */
class UserReportController extends Controller
{
  /**
   * Create a new controller instance.
   *
   * @return void
   */
  public function __construct()
  {
    $this->middleware('auth:sanctum');
  }

  /**
   * Display a listing of the user reports (Admin only).
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function index(Request $request)
  {
    // Check if user is admin
    if (!Auth::user()->hasRole('admin')) {
      return response()->json(['message' => 'Unauthorized'], 403);
    }

    $query = UserReport::with(['reporter', 'reportedUser', 'topic', 'reviewedBy']);

    // Filter by status
    if ($request->has('status')) {
      $query->where('status', $request->status);
    }

    // Filter by date range
    if ($request->has('from_date')) {
      $query->where('created_at', '>=', Carbon::parse($request->from_date));
    }
    if ($request->has('to_date')) {
      $query->where('created_at', '<=', Carbon::parse($request->to_date));
    }

    $reports = $query->orderBy('created_at', 'desc')->paginate(15);

    return response()->json($reports);
  }

  /**
   * Show the form for creating a new user report.
   *
   * @return void
   */
  public function create()
  {
    //
  }

  /**
   * Store a newly created user report in storage.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function store(Request $request)
  {
    // Validate request
    $request->validate([
      'reported_user_id' => 'nullable|exists:cyo_auth_accounts,id',
      'topic_id' => 'nullable|exists:cyo_topics,id',
      'story_id' => 'nullable|exists:cyo_stories,id',
      'reason' => 'nullable|string|min:1',
    ]);

    $reportedUserId = $request->reported_user_id;

    // If reported_user_id is missing, try to resolve from topic or story
    if (!$reportedUserId) {
      if ($request->topic_id) {
        $topic = Topic::find($request->topic_id);
        $reportedUserId = $topic ? $topic->user_id : null;
      } elseif ($request->story_id) {
        $story = Story::find($request->story_id);
        $reportedUserId = $story ? $story->user_id : null;
      }
    }

    if (!$reportedUserId) {
      return response()->json([
        'message' => 'Cannot determine user to report'
      ], 400);
    }

    // Check if user is reporting themselves
    if ($reportedUserId == Auth::id()) {
      return response()->json([
        'message' => 'You cannot report yourself'
      ], 400);
    }

    // Check for duplicate reports
    $existingReport = UserReport::where([
      'user_id' => Auth::id(),
      'reported_user_id' => $reportedUserId,
      'topic_id' => $request->topic_id,
      'story_id' => $request->story_id,
      'status' => 'pending'
    ])->first();

    if ($existingReport) {
      return response()->json([
        'message' => 'You have already reported this user/content'
      ], 400);
    }

    // Create report
    $report = UserReport::create([
      'user_id' => Auth::id(),
      'reported_user_id' => $reportedUserId,
      'topic_id' => $request->topic_id,
      'story_id' => $request->story_id,
      'reason' => $request->reason,
      'status' => 'pending'
    ]);

    return response()->json([
      'message' => 'Report submitted successfully',
      'report' => $report
    ]);
  }

  /**
   * Display the specified user report.
   *
   * @param  \App\Models\UserReport  $userReport
   * @return void
   */
  public function show(UserReport $userReport)
  {
    //
  }

  /**
   * Show the form for editing the specified user report.
   *
   * @param  \App\Models\UserReport  $userReport
   * @return void
   */
  public function edit(UserReport $userReport)
  {
    //
  }

  /**
   * Update the specified user report in storage.
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  \App\Models\UserReport  $userReport
   * @return void
   */
  public function update(Request $request, UserReport $userReport)
  {
    //
  }

  /**
   * Remove the specified user report from storage.
   *
   * @param  \App\Models\UserReport  $userReport
   * @return void
   */
  public function destroy(UserReport $userReport)
  {
    //
  }

  /**
   * Review a user report (Admin only).
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  \App\Models\UserReport  $report
   * @return \Illuminate\Http\JsonResponse
   */
  public function review(Request $request, UserReport $report)
  {
    // Check if user is admin
    if (!Auth::user()->hasRole('admin')) {
      return response()->json(['message' => 'Unauthorized'], 403);
    }

    // Validate request
    $request->validate([
      'status' => 'required|in:reviewed,resolved,dismissed',
      'admin_notes' => 'required|string',
      'ban_user' => 'boolean',
      'ban_duration' => 'required_if:ban_user,true|integer|min:1',  // Duration in days
    ]);

    DB::beginTransaction();
    try {
      // Update report
      $report->update([
        'status' => $request->status,
        'admin_notes' => $request->admin_notes,
        'reviewed_by' => Auth::id(),
        'reviewed_at' => now()
      ]);

      // Ban user if requested
      if ($request->ban_user) {
        $reportedUser = AuthAccount::find($report->reported_user_id);
        $reportedUser->update([
          'banned_until' => Carbon::now()->addDays($request->ban_duration),
          'ban_reason' => $request->admin_notes
        ]);

        // If there's a topic involved, hide it
        if ($report->topic_id) {
          Topic::where('id', $report->topic_id)->update(['hidden' => true]);
        }
      }

      DB::commit();

      return response()->json([
        'message' => 'Report reviewed successfully',
        'report' => $report->load(['reporter', 'reportedUser', 'topic', 'reviewedBy'])
      ]);
    } catch (\Exception $e) {
      DB::rollback();
      return response()->json([
        'message' => 'An error occurred while reviewing the report',
        'error' => $e->getMessage()
      ], 500);
    }
  }

  /**
   * Get user report statistics (Admin only).
   *
   * @return \Illuminate\Http\JsonResponse
   */
  public function getStats()
  {
    // Check if user is admin
    if (!Auth::user()->hasRole('admin')) {
      return response()->json(['message' => 'Unauthorized'], 403);
    }

    $stats = [
      'total' => UserReport::count(),
      'pending' => UserReport::where('status', 'pending')->count(),
      'reviewed' => UserReport::where('status', 'reviewed')->count(),
      'resolved' => UserReport::where('status', 'resolved')->count(),
      'dismissed' => UserReport::where('status', 'dismissed')->count(),
      'recent' => UserReport::where('created_at', '>=', Carbon::now()->subDays(7))->count(),
      'most_reported_users' => UserReport::select('reported_user_id', DB::raw('count(*) as total'))
        ->with('reportedUser')
        ->groupBy('reported_user_id')
        ->orderBy('total', 'desc')
        ->limit(5)
        ->get()
    ];

    return response()->json($stats);
  }
}
