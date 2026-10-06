<?php

namespace App\Http\Controllers;

use App\Models\ViolationReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Student / class violation reports: sent from the app's "Báo cáo vi phạm"
 * flow (store), handled by admins in the panel's "Vi phạm học sinh & lớp"
 * page (index, review, destroy).
 */
class ViolationReportController extends Controller
{
  public function store(Request $request)
  {
    $data = $request->validate([
      'type' => 'required|in:' . implode(',', ViolationReport::TYPES),
      'subject_name' => 'required|string|max:255',
      'violation_type' => 'nullable|string|max:255',
      'report_date' => 'nullable|date',
      'notes' => 'nullable|string|max:2000',
      'absences' => 'nullable|integer|min:0|max:1000',
      'cleanliness' => 'nullable|boolean',
      'uniform' => 'nullable|boolean',
    ]);

    $report = ViolationReport::create($data + [
      'reporter_id' => Auth::id(),
      'status' => 'pending',
    ]);

    return response()->json(['message' => 'Đã gửi báo cáo vi phạm.', 'report' => $report], 201);
  }

  public function index(Request $request)
  {
    $query = ViolationReport::with(['reporter:id,username', 'reporter.profile:id,auth_account_id,profile_name', 'reviewedBy:id,username']);

    if ($request->filled('type')) {
      $query->where('type', $request->type);
    }
    if ($request->filled('status')) {
      $query->where('status', $request->status);
    }
    if ($request->filled('search')) {
      $search = trim((string) $request->search);
      $query->where(function ($q) use ($search) {
        $q->where('subject_name', 'like', "%{$search}%")
          ->orWhere('violation_type', 'like', "%{$search}%")
          ->orWhere('notes', 'like', "%{$search}%");
      });
    }

    $perPage = min(max((int) $request->input('per_page', 15), 1), 100);

    return response()->json($query->orderByDesc('id')->paginate($perPage));
  }

  public function review(Request $request, $id)
  {
    $data = $request->validate([
      'status' => 'required|in:reviewed,resolved,dismissed',
      'admin_notes' => 'nullable|string|max:2000',
    ]);

    $report = ViolationReport::findOrFail($id);
    $report->update($data + [
      'reviewed_by' => Auth::id(),
      'reviewed_at' => now(),
    ]);

    return response()->json(['message' => 'Đã cập nhật báo cáo vi phạm.', 'report' => $report]);
  }

  public function destroy($id)
  {
    ViolationReport::findOrFail($id)->delete();

    return response()->json(['message' => 'Đã xóa báo cáo vi phạm.']);
  }
}
