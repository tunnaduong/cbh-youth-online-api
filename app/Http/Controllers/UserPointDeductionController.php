<?php

namespace App\Http\Controllers;

use App\Models\UserPointDeduction;
use App\Models\AuthAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class UserPointDeductionController extends Controller
{
  /**
   * Display a listing of point deductions.
   *
   * @return \Illuminate\Http\JsonResponse
   */
  public function index(Request $request)
  {
    // Check if user is admin
    if (!Auth::user()->hasRole('admin')) {
      return response()->json(['message' => 'Unauthorized'], 403);
    }

    $query = UserPointDeduction::with(['user', 'admin']);

    // Filter by user if provided
    if ($request->has('user_id')) {
      $query->where('user_id', $request->user_id);
    }

    // Filter by active status
    if ($request->has('active_only') && $request->active_only) {
      $query->active();
    }

    // Filter by reason
    if ($request->has('reason')) {
      $query->where('reason', $request->reason);
    }

    $deductions = $query->orderBy('created_at', 'desc')->paginate(15);

    return response()->json($deductions);
  }

  /**
   * Store a newly created point deduction.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function store(Request $request)
  {
    // Check if user is admin
    if (!Auth::user()->hasRole('admin')) {
      return response()->json(['message' => 'Unauthorized'], 403);
    }

    $validator = Validator::make($request->all(), [
      'user_id' => 'required|exists:cyo_auth_accounts,id',
      'points_deducted' => 'required|integer|min:1|max:1000',
      'reason' => 'required|string|in:' . implode(',', UserPointDeduction::DEDUCTION_REASONS),
      'description' => 'nullable|string|max:1000',
      'expires_at' => 'nullable|date|after:now'
    ]);

    if ($validator->fails()) {
      return response()->json(['errors' => $validator->errors()], 422);
    }

    $deduction = UserPointDeduction::create([
      'user_id' => $request->user_id,
      'points_deducted' => $request->points_deducted,
      'reason' => $request->reason,
      'description' => $request->description,
      'admin_id' => Auth::id(),
      'expires_at' => $request->expires_at ? Carbon::parse($request->expires_at) : null
    ]);

    return response()->json([
      'message' => 'Point deduction applied successfully',
      'deduction' => $deduction->load(['user', 'admin'])
    ], 201);
  }

  /**
   * Display the specified point deduction.
   *
   * @param  int  $id
   * @return \Illuminate\Http\JsonResponse
   */
  public function show($id)
  {
    // Check if user is admin
    if (!Auth::user()->hasRole('admin')) {
      return response()->json(['message' => 'Unauthorized'], 403);
    }

    $deduction = UserPointDeduction::with(['user', 'admin'])->findOrFail($id);

    return response()->json($deduction);
  }

  /**
   * Update the specified point deduction.
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  int  $id
   * @return \Illuminate\Http\JsonResponse
   */
  public function update(Request $request, $id)
  {
    // Check if user is admin
    if (!Auth::user()->hasRole('admin')) {
      return response()->json(['message' => 'Unauthorized'], 403);
    }

    $deduction = UserPointDeduction::findOrFail($id);

    $validator = Validator::make($request->all(), [
      'points_deducted' => 'integer|min:1|max:1000',
      'reason' => 'string|in:' . implode(',', UserPointDeduction::DEDUCTION_REASONS),
      'description' => 'nullable|string|max:1000',
      'is_active' => 'boolean',
      'expires_at' => 'nullable|date'
    ]);

    if ($validator->fails()) {
      return response()->json(['errors' => $validator->errors()], 422);
    }

    $deduction->update($request->only([
      'points_deducted',
      'reason',
      'description',
      'is_active',
      'expires_at'
    ]));

    return response()->json([
      'message' => 'Point deduction updated successfully',
      'deduction' => $deduction->load(['user', 'admin'])
    ]);
  }

  /**
   * Remove the specified point deduction.
   *
   * @param  int  $id
   * @return \Illuminate\Http\JsonResponse
   */
  public function destroy($id)
  {
    // Check if user is admin
    if (!Auth::user()->hasRole('admin')) {
      return response()->json(['message' => 'Unauthorized'], 403);
    }

    $deduction = UserPointDeduction::findOrFail($id);
    $deduction->delete();

    return response()->json(['message' => 'Point deduction removed successfully']);
  }

  /**
   * Get point deductions for a specific user.
   *
   * @param  int  $userId
   * @return \Illuminate\Http\JsonResponse
   */
  public function getUserDeductions($userId)
  {
    // Check if user is admin or the user themselves
    if (!Auth::user()->hasRole('admin') && Auth::id() != $userId) {
      return response()->json(['message' => 'Unauthorized'], 403);
    }

    $deductions = UserPointDeduction::with(['admin'])
      ->where('user_id', $userId)
      ->orderBy('created_at', 'desc')
      ->paginate(10);

    return response()->json($deductions);
  }

  /**
   * Get total active deductions for a user.
   *
   * @param  int  $userId
   * @return \Illuminate\Http\JsonResponse
   */
  public function getUserTotalDeductions($userId)
  {
    // Check if user is admin or the user themselves
    if (!Auth::user()->hasRole('admin') && Auth::id() != $userId) {
      return response()->json(['message' => 'Unauthorized'], 403);
    }

    $totalDeductions = UserPointDeduction::getTotalActiveDeductions($userId);

    return response()->json(['total_deductions' => $totalDeductions]);
  }

  /**
   * Reverse a point deduction (set as inactive).
   *
   * @param  int  $id
   * @return \Illuminate\Http\JsonResponse
   */
  public function reverse($id)
  {
    // Check if user is admin
    if (!Auth::user()->hasRole('admin')) {
      return response()->json(['message' => 'Unauthorized'], 403);
    }

    $deduction = UserPointDeduction::findOrFail($id);
    $deduction->update(['is_active' => false]);

    return response()->json([
      'message' => 'Point deduction reversed successfully',
      'deduction' => $deduction->load(['user', 'admin'])
    ]);
  }

  /**
   * Get deduction statistics.
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
      'total_deductions' => UserPointDeduction::count(),
      'active_deductions' => UserPointDeduction::active()->count(),
      'total_points_deducted' => UserPointDeduction::active()->sum('points_deducted'),
      'deductions_by_reason' => UserPointDeduction::active()
        ->selectRaw('reason, count(*) as count, sum(points_deducted) as total_points')
        ->groupBy('reason')
        ->get()
    ];

    return response()->json($stats);
  }
}
