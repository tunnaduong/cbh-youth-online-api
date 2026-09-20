<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ModerationQueue;
use App\Models\Topic;
use App\Models\TopicComment;
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

        $items = $query->paginate(20);

        return response()->json($items);
    }

    /**
     * Approve a queued item (make it visible).
     * POST /api/admin/moderation/{id}/approve
     */
    public function approve(Request $request, int $id)
    {
        $entry = ModerationQueue::findOrFail($id);

        if ($entry->content_type === 'topic') {
            Topic::where('id', $entry->content_id)->update([
                'moderation_status' => 'approved',
                'hidden' => false,
            ]);
        } elseif ($entry->content_type === 'comment') {
            TopicComment::where('id', $entry->content_id)->update([
                'moderation_status' => 'approved',
            ]);
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

        if ($entry->content_type === 'topic') {
            Topic::where('id', $entry->content_id)->update([
                'moderation_status' => 'rejected',
                'hidden' => true,
            ]);
        } elseif ($entry->content_type === 'comment') {
            TopicComment::where('id', $entry->content_id)->update([
                'moderation_status' => 'rejected',
            ]);
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
