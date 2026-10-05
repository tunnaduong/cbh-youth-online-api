<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Admin panel: the audit log (cyo_audit_logs). Read-only on purpose - a log
 * that can be edited proves nothing - except for an entry's status.
 */
class AuditLogController extends Controller
{
  /**
   * Entries, newest first. Filters: search (username, or a user id),
   * action_type, status, target_type + target_id, from / to (dates).
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function index(Request $request)
  {
    $request->validate([
      'status' => ['nullable', Rule::in(AuditLog::STATUSES)],
      'from' => 'nullable|date',
      'to' => 'nullable|date',
      'target_id' => 'nullable|integer',
    ]);

    $query = AuditLog::query()
      ->with([
        'user:id,username',
        'user.profile:id,auth_account_id,profile_name',
        'actor:id,username',
      ]);

    $search = trim((string) $request->input('search', ''));
    if ($search !== '') {
      $query->where(function ($q) use ($search) {
        $q->whereHas('user', fn($u) => $u->where('username', 'like', "%{$search}%"))
          ->orWhereHas('actor', fn($u) => $u->where('username', 'like', "%{$search}%"));
        if (ctype_digit($search)) {
          $q->orWhere('user_id', (int) $search)->orWhere('actor_id', (int) $search);
        }
      });
    }

    foreach (['action_type', 'status', 'target_type', 'target_id'] as $field) {
      if ($request->filled($field)) {
        $query->where($field, $request->input($field));
      }
    }
    if ($request->filled('from')) {
      $query->where('created_at', '>=', $request->date('from')->startOfDay());
    }
    if ($request->filled('to')) {
      $query->where('created_at', '<=', $request->date('to')->endOfDay());
    }

    $perPage = min(max((int) $request->input('per_page', 20), 1), 100);

    return response()->json(
      $query->orderByDesc('id')->paginate($perPage)->through(fn(AuditLog $log) => [
        'id' => $log->id,
        'action_type' => $log->action_type,
        'status' => $log->status,
        'target_type' => $log->target_type,
        'target_id' => $log->target_id,
        'old_data' => $log->old_data,
        'new_data' => $log->new_data,
        'created_at' => $log->created_at,
        'updated_at' => $log->updated_at,
        'user' => $log->user ? [
          'id' => $log->user->id,
          'username' => $log->user->username,
          'profile_name' => $log->user->profile->profile_name ?? null,
        ] : null,
        // Null when the user acted on their own data.
        'actor' => $log->actor ? [
          'id' => $log->actor->id,
          'username' => $log->actor->username,
        ] : null,
      ])
    );
  }

  /**
   * The action types present in the log, for the filter.
   *
   * @return \Illuminate\Http\JsonResponse
   */
  public function actionTypes()
  {
    return response()->json([
      'action_types' => AuditLog::query()->distinct()->orderBy('action_type')->pluck('action_type'),
    ]);
  }

  /**
   * Change an entry's status - the only thing about an entry that may change.
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  int  $id
   * @return \Illuminate\Http\JsonResponse
   */
  public function updateStatus(Request $request, $id)
  {
    $data = $request->validate([
      'status' => ['required', Rule::in(AuditLog::STATUSES)],
    ]);

    $log = AuditLog::findOrFail($id);
    $log->status = $data['status'];
    $log->save();

    return response()->json([
      'message' => 'Đã cập nhật trạng thái.',
      'status' => $log->status,
      'updated_at' => $log->updated_at,
    ]);
  }
}
