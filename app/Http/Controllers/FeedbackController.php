<?php

namespace App\Http\Controllers;

use App\Mail\FeedbackReceivedMail;
use App\Models\AuthAccount;
use App\Models\Feedback;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

/**
 * In-app bug reports & suggestions (replaces the old Google Form).
 */
class FeedbackController extends Controller
{
  /**
   * POST /v1.0/feedback - open to guests (optional auth); guests must leave
   * an email so we have a way to get back to them.
   */
  public function store(Request $request)
  {
    $user = $request->user();

    $validated = $request->validate([
      'type' => ['required', Rule::in(Feedback::TYPES)],
      'content' => 'required|string|min:10|max:5000',
      'image_urls' => 'nullable|array|max:4',
      'image_urls.*' => 'string|url|starts_with:https://,http://|max:500',
      'contact_email' => [$user ? 'nullable' : 'required', 'email', 'max:255'],
      'platform' => ['required', Rule::in(Feedback::PLATFORMS)],
      'app_version' => 'nullable|string|max:50',
      'device_info' => 'nullable|string|max:255',
      'page_url' => 'nullable|string|max:500',
    ], [
      'content.required' => 'Vui lòng nhập nội dung.',
      'content.min' => 'Nội dung cần ít nhất 10 ký tự để chúng mình hiểu rõ vấn đề.',
      'content.max' => 'Nội dung tối đa 5000 ký tự.',
      'image_urls.max' => 'Chỉ được đính kèm tối đa 4 ảnh.',
      'contact_email.required' => 'Vui lòng nhập email để chúng mình có thể phản hồi bạn.',
      'contact_email.email' => 'Email không hợp lệ.',
    ]);

    $feedback = Feedback::create([
      ...$validated,
      'user_id' => $user?->id,
      'image_urls' => array_values($validated['image_urls'] ?? []) ?: null,
      'status' => 'new',
      'ip_address' => $request->ip(),
    ]);

    $this->notifyAdmins($feedback);

    return response()->json([
      'message' => 'Cảm ơn bạn đã gửi góp ý! Đội ngũ phát triển sẽ xem xét sớm nhất.',
      'feedback' => $feedback,
    ], 201);
  }

  /**
   * Email every admin about the new submission. A mail failure must never
   * fail the user's submit - it's already saved and visible in /admin/feedback.
   */
  private function notifyAdmins(Feedback $feedback): void
  {
    $feedback->load('user:id,username,email');

    $emails = AuthAccount::where('role', 'admin')
      ->whereNotNull('email')
      ->pluck('email')
      ->filter()
      ->unique();

    foreach ($emails as $email) {
      try {
        Mail::to($email)->queue(new FeedbackReceivedMail($feedback));
      } catch (\Throwable $e) {
        Log::error('Failed to email admin about new feedback', [
          'feedback_id' => $feedback->id,
          'error' => $e->getMessage(),
        ]);
      }
    }
  }

  /**
   * GET /v1.0/feedback/mine - the signed-in user's own submissions.
   */
  public function mine(Request $request)
  {
    return response()->json(
      Feedback::where('user_id', $request->user()->id)
        ->latest()
        ->paginate(20)
    );
  }

  // --- Admin ---

  /**
   * GET /v1.0/admin/feedback?status=&type=&platform=&search=
   */
  public function adminIndex(Request $request)
  {
    $query = Feedback::with(['user:id,username,email', 'reviewer:id,username'])->latest();

    foreach (['status', 'type', 'platform'] as $filter) {
      if ($request->filled($filter)) {
        $query->where($filter, $request->input($filter));
      }
    }

    if ($request->filled('search')) {
      $term = '%' . $request->input('search') . '%';
      $query->where(function ($q) use ($term) {
        $q->where('content', 'like', $term)
          ->orWhere('contact_email', 'like', $term)
          ->orWhereHas('user', fn($u) => $u->where('username', 'like', $term));
      });
    }

    return response()->json(
      $query->paginate(min(max((int) $request->input('per_page', 20), 1), 100))
    );
  }

  /**
   * GET /v1.0/admin/feedback/stats
   */
  public function adminStats()
  {
    $byStatus = Feedback::selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
    $byType = Feedback::selectRaw('type, COUNT(*) as total')->groupBy('type')->pluck('total', 'type');

    return response()->json([
      'total' => (int) $byStatus->sum(),
      'by_status' => collect(Feedback::STATUSES)->mapWithKeys(fn($s) => [$s => (int) ($byStatus[$s] ?? 0)]),
      'by_type' => collect(Feedback::TYPES)->mapWithKeys(fn($t) => [$t => (int) ($byType[$t] ?? 0)]),
    ]);
  }

  /**
   * PATCH /v1.0/admin/feedback/{id}
   */
  public function adminUpdate(Request $request, $id)
  {
    $validated = $request->validate([
      'status' => ['required', Rule::in(Feedback::STATUSES)],
      'admin_notes' => 'nullable|string|max:2000',
    ]);

    $feedback = Feedback::findOrFail($id);
    $feedback->update([
      ...$validated,
      'reviewed_by' => $request->user()->id,
      'reviewed_at' => now(),
    ]);

    return response()->json([
      'message' => 'Đã cập nhật góp ý.',
      'feedback' => $feedback->load(['user:id,username,email', 'reviewer:id,username']),
    ]);
  }

  /**
   * DELETE /v1.0/admin/feedback/{id}
   */
  public function adminDestroy($id)
  {
    Feedback::findOrFail($id)->delete();

    return response()->json(['message' => 'Đã xóa góp ý.']);
  }
}
