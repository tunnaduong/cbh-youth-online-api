<?php

namespace App\Http\Controllers\Admin;

use App\Events\MessageDeleted;
use App\Http\Controllers\Controller;
use App\Models\AdminMessageAccessLog;
use App\Models\AuditLog;
use App\Models\Message;
use App\Models\Story;
use App\Models\Topic;
use App\Models\TopicComment;
use App\Models\UserReport;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Admin actions on one piece of user content - a post, comment, chat message
 * or story - from the panel (reports, posts, comments, chats):
 *
 *  - warn:   the author gets a `content_warning` notification (web + push)
 *            that opens the content, telling them it breaks the community
 *            rules. The content stays.
 *  - remove: the content is deleted and the author gets `content_deleted`
 *            (unless `notify` is false).
 *
 * Both can close a report in the same call (`report_id`).
 */
class ContentModerationController extends Controller
{
  private const TYPES = 'topic,comment,message,story';

  public function warn(Request $request)
  {
    $data = $this->validated($request);
    [$content, $authorId] = $this->resolve($data['content_type'], (int) $data['content_id']);
    if (!$content) {
      return response()->json(['message' => 'Không tìm thấy nội dung này (có thể đã bị xóa).'], 404);
    }
    if (!$authorId) {
      return response()->json(['message' => 'Nội dung này không có người đăng để cảnh cáo.'], 400);
    }

    $note = trim((string) ($data['note'] ?? ''));
    NotificationService::createModerationActionNotification($authorId, 'content_warning', $data['content_type'], $content, $note);

    AuditLog::record('content_warned', $authorId, [
      'target_type' => $data['content_type'],
      'target_id' => $content->id,
      'new' => ['note' => $note],
    ]);

    $this->closeReport($data['report_id'] ?? null, $note !== '' ? $note : 'Đã cảnh cáo người đăng.');

    return response()->json(['message' => 'Đã gửi cảnh cáo tới người đăng.']);
  }

  public function remove(Request $request)
  {
    $data = $this->validated($request) + $request->validate(['notify' => 'sometimes|boolean']);
    [$content, $authorId] = $this->resolve($data['content_type'], (int) $data['content_id']);
    if (!$content) {
      return response()->json(['message' => 'Không tìm thấy nội dung này (có thể đã bị xóa).'], 404);
    }

    $note = trim((string) ($data['note'] ?? ''));

    // Notify first: the notice needs the content (its text, its post) loaded.
    if ($authorId && ($data['notify'] ?? true)) {
      NotificationService::createModerationActionNotification($authorId, 'content_deleted', $data['content_type'], $content, $note);
    }

    DB::transaction(function () use ($data, $content, $request) {
      switch ($data['content_type']) {
        case 'message':
          AdminMessageAccessLog::create([
            'admin_id' => Auth::id(),
            'conversation_id' => $content->conversation_id,
            'action' => 'delete_message',
            'query' => "message:{$content->id}",
            'ip' => $request->ip(),
          ]);
          if (!$content->trashed()) {
            $content->delete();
          }
          break;
        default:
          $content->delete();
      }
    });

    if ($data['content_type'] === 'message') {
      // Participants with the chat open get it removed live.
      broadcast(new MessageDeleted($content->conversation_id, $content->id));
    }

    AuditLog::record('content_removed', $authorId, [
      'target_type' => $data['content_type'],
      'target_id' => $content->id,
      'new' => ['note' => $note],
    ]);

    $this->closeReport($data['report_id'] ?? null, $note !== '' ? $note : 'Đã xóa nội dung vi phạm.');

    return response()->json(['message' => 'Đã xóa nội dung.']);
  }

  private function validated(Request $request): array
  {
    return $request->validate([
      'content_type' => 'required|in:' . self::TYPES,
      'content_id' => 'required|integer',
      'note' => 'nullable|string|max:500',
      'report_id' => 'nullable|integer|exists:cyo_user_reports,id',
    ]);
  }

  /**
   * The content and its author's id ([null, null] when it is gone).
   */
  private function resolve(string $type, int $id): array
  {
    $content = match ($type) {
      'topic' => Topic::with('user:id,username')->find($id),
      'comment' => TopicComment::with('topic.user:id,username')->find($id),
      'message' => Message::withTrashed()->find($id),
      'story' => Story::find($id),
    };

    return [$content, $content?->user_id];
  }

  private function closeReport(?int $reportId, string $notes): void
  {
    if (!$reportId) {
      return;
    }

    UserReport::where('id', $reportId)->update([
      'status' => 'resolved',
      'admin_notes' => $notes,
      'reviewed_by' => Auth::id(),
      'reviewed_at' => now(),
    ]);
  }
}
