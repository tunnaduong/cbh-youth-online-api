<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ModerationQueue;
use App\Models\Topic;
use App\Models\TopicComment;
use App\Services\ContentModerationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ModerationController extends Controller
{
    /**
     * List moderation queue items.
     * GET /api/admin/moderation?status=pending&type=topic&page=1
     */
    public function index(Request $request)
    {
        $query = ModerationQueue::with(['user:id,username'])
            ->orderBy('created_at', 'asc');

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->type) {
            $query->where('content_type', $request->type);
        }

        $items = $query->paginate(min(max((int) $request->input('per_page', 20), 1), 100));

        $this->attachContentContext($items->getCollection());

        return response()->json($items);
    }

    /**
     * Decorate queue rows with what a reviewer needs but the snapshot doesn't
     * carry: a link to the live content, and whether it has attachments the
     * AI couldn't read (which is often the very reason it was queued).
     *
     * Rows whose content was hard-deleted - the auto-rejected ones - simply
     * get a null link; the snapshot text is all that's left of them.
     *
     * @param  \Illuminate\Support\Collection<int, ModerationQueue>  $rows
     */
    private function attachContentContext($rows): void
    {
        if ($rows->isEmpty()) {
            return;
        }

        $commentIds = $rows->where('content_type', 'comment')->pluck('content_id')->unique();
        $comments = $commentIds->isEmpty()
            ? collect()
            : TopicComment::whereIn('id', $commentIds)->get(['id', 'topic_id', 'image_urls'])->keyBy('id');

        // Topics to resolve: the queued ones, plus the ones the queued
        // comments live on (so a comment can link back to its post).
        $topicIds = $rows->where('content_type', 'topic')->pluck('content_id')
            ->merge($comments->pluck('topic_id'))
            ->filter()
            ->unique();

        $topics = $topicIds->isEmpty()
            ? collect()
            : Topic::with('author:id,username')
                ->whereIn('id', $topicIds)
                ->get(['id', 'title', 'user_id', 'anonymous', 'cdn_image_id', 'cdn_document_id', 'cdn_video_id'])
                ->keyBy('id');

        foreach ($rows as $row) {
            $comment = $row->content_type === 'comment' ? $comments->get($row->content_id) : null;
            $topicId = $row->content_type === 'topic' ? $row->content_id : $comment?->topic_id;
            $topic = $topicId ? $topics->get($topicId) : null;

            $row->setAttribute('topic', $topic ? [
                'id' => $topic->id,
                'title' => $topic->title,
                // Anonymous posts live under the literal /anonymous/ path.
                'username' => $topic->anonymous ? 'anonymous' : $topic->author?->username,
            ] : null);

            $row->setAttribute('has_attachments', $comment
                ? !empty($comment->image_urls)
                : (bool) ($topic && $topic->hasAttachments()));
        }
    }

    /**
     * Approve a queued item (make it visible).
     * POST /api/admin/moderation/{id}/approve
     */
    public function approve(Request $request, int $id)
    {
        $entry = ModerationQueue::findOrFail($id);

        if ($entry->content_type === 'topic') {
            // Restore the author's own visibility choice rather than forcing
            // the post public - holding it for review is what set hidden=true.
            $snapshot = json_decode($entry->content_snapshot, true);
            $originalHidden = (bool) ($snapshot['original_hidden'] ?? false);

            Topic::where('id', $entry->content_id)->update([
                'moderation_status' => 'approved',
                'hidden' => $originalHidden,
            ]);

            if ($topic = Topic::find($entry->content_id)) {
                ContentModerationService::sendApprovedEmail($topic, 'topic');
                ContentModerationService::notifyAuthorApproved($topic, 'topic');
            }
        } elseif ($entry->content_type === 'comment') {
            TopicComment::where('id', $entry->content_id)->update([
                'moderation_status' => 'approved',
            ]);

            if ($comment = TopicComment::find($entry->content_id)) {
                ContentModerationService::sendApprovedEmail($comment, 'comment');
                ContentModerationService::notifyAuthorApproved($comment, 'comment');
            }
        }

        $entry->update([
            'status' => 'approved',
            'reviewed_by' => Auth::id(),
            'reviewer_note' => $request->note,
            'reviewed_at' => now(),
        ]);

        return response()->json(['message' => 'Đã duyệt nội dung.']);
    }

    /**
     * Reject a queued item (keep hidden/deleted).
     * POST /api/admin/moderation/{id}/reject
     */
    public function reject(Request $request, int $id)
    {
        $entry = ModerationQueue::findOrFail($id);

        // The reviewer's own note is what the author is told; the AI's
        // internal reason isn't written for them to read.
        $reason = trim((string) $request->note);

        if ($entry->content_type === 'topic') {
            Topic::where('id', $entry->content_id)->update([
                'moderation_status' => 'rejected',
                'hidden' => true,
            ]);

            if ($topic = Topic::find($entry->content_id)) {
                ContentModerationService::notifyAuthorRejected($topic, 'topic', $reason);
            }
        } elseif ($entry->content_type === 'comment') {
            TopicComment::where('id', $entry->content_id)->update([
                'moderation_status' => 'rejected',
            ]);

            if ($comment = TopicComment::find($entry->content_id)) {
                ContentModerationService::notifyAuthorRejected($comment, 'comment', $reason);
            }
        }

        $entry->update([
            'status' => 'rejected',
            'reviewed_by' => Auth::id(),
            'reviewer_note' => $request->note,
            'reviewed_at' => now(),
        ]);

        return response()->json(['message' => 'Đã từ chối nội dung.']);
    }

    /**
     * Queue stats summary.
     * GET /api/admin/moderation/stats
     */
    public function stats()
    {
        return response()->json([
            'pending' => ModerationQueue::where('status', 'pending')->count(),
            'approved_today' => ModerationQueue::where('status', 'approved')
                ->whereDate('reviewed_at', today())->count(),
            'rejected_today' => ModerationQueue::where('status', 'rejected')
                ->whereDate('reviewed_at', today())->count(),
            'auto_rejected' => ModerationQueue::where('status', 'rejected')
                ->whereNull('reviewed_by')->count(),
        ]);
    }
}
