<?php

namespace App\Http\Controllers;

use App\Models\AuthAccount;
use App\Models\Message;
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

    // topic.author lets the admin table link straight to the reported post.
    // The message (and its conversation) is loaded so a reported chat message
    // can be read in context without opening the whole thread.
    $query = UserReport::with([
      'reporter',
      'reportedUser',
      'topic',
      'topic.author:id,username',
      'message',
      'message.conversation:id,name,type,is_public',
      'reviewedBy',
    ]);

    // Filter by status
    if ($request->has('status')) {
      $query->where('status', $request->status);
    }

    // Filter by what was reported, so message reports can be triaged on their
    // own - they're handled differently from post or story reports.
    switch ($request->input('type')) {
      case 'message':
        $query->whereNotNull('message_id');
        break;
      case 'topic':
        $query->whereNotNull('topic_id');
        break;
      case 'story':
        $query->whereNotNull('story_id');
        break;
      case 'user':
        $query->whereNull('message_id')->whereNull('topic_id')->whereNull('story_id');
        break;
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
      'message_id' => 'nullable|exists:cyo_conversation_messages,id',
      'reason' => 'nullable|string|min:1',
    ]);

    // A message report is only legitimate from someone who can actually see
    // the message, otherwise any id could be probed to learn who sent what.
    // The reported user always comes from the message itself rather than the
    // request, so it can't be pinned on a bystander.
    if ($request->message_id) {
      $message = Message::withTrashed()->with('conversation')->find($request->message_id);

      if (!$message || !$message->conversation || !$message->conversation->isAccessibleBy(Auth::id())) {
        return response()->json([
          'message' => 'Message not found'
        ], 404);
      }

      if (!$message->user_id) {
        return response()->json([
          'message' => 'This message cannot be reported'
        ], 400);
      }

      $request->merge(['reported_user_id' => $message->user_id]);
    }

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

    $duplicateQuery = UserReport::where('user_id', Auth::id())
      ->where('reported_user_id', $reportedUserId)
      ->where('status', 'pending');

    if ($request->message_id) {
      $duplicateQuery->where('message_id', $request->message_id);
    } elseif ($request->topic_id) {
      $duplicateQuery->where('topic_id', $request->topic_id)
        ->whereNull('story_id')
        ->whereNull('message_id');
    } elseif ($request->story_id) {
      $duplicateQuery->where('story_id', $request->story_id)
        ->whereNull('topic_id')
        ->whereNull('message_id');
    } else {
      $duplicateQuery->whereNull('topic_id')
        ->whereNull('story_id')
        ->whereNull('message_id');
    }

    $existingReport = $duplicateQuery->first();

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
      'message_id' => $request->message_id,
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

        // Same idea for a reported chat message: recalling it is what clients
        // already know how to render, so it disappears everywhere at once.
        if ($report->message_id) {
          Message::where('id', $report->message_id)->update(['is_recalled' => true]);
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
      'messages' => UserReport::whereNotNull('message_id')->count(),
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
