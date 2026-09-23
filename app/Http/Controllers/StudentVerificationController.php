<?php

namespace App\Http\Controllers;

use App\Mail\StudentVerificationApprovedMail;
use App\Mail\StudentVerificationRejectedMail;
use App\Models\StudentVerification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class StudentVerificationController extends Controller
{
    const DISCOUNT_PERCENT = 10;

    public function submit(Request $request)
    {
        $user = $request->user();

        if ($user->student_verified_at) {
            return response()->json(['message' => 'Tài khoản của bạn đã được xác minh học sinh.'], 422);
        }

        $existing = StudentVerification::where('user_id', $user->id)
            ->where('status', 'pending')
            ->first();

        if ($existing) {
            return response()->json(['message' => 'Yêu cầu xác minh của bạn đang chờ xét duyệt.'], 422);
        }

        $request->validate([
            'selfie_url' => 'required|string|url',
            'student_card_url' => 'required|string|url',
        ]);

        // Replace any rejected previous request
        StudentVerification::where('user_id', $user->id)->where('status', 'rejected')->delete();

        $verification = StudentVerification::create([
            'user_id' => $user->id,
            'selfie_url' => $request->selfie_url,
            'student_card_url' => $request->student_card_url,
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Gửi yêu cầu xác minh thành công. Admin sẽ xét duyệt trong vòng 24 giờ.',
            'verification' => $verification,
        ], 201);
    }

    public function status(Request $request)
    {
        $user = $request->user();

        $verification = StudentVerification::where('user_id', $user->id)
            ->latest()
            ->first();

        return response()->json([
            'is_verified' => (bool) $user->student_verified_at,
            'verified_at' => $user->student_verified_at,
            'discount_percent' => $user->student_verified_at ? self::DISCOUNT_PERCENT : 0,
            'verification' => $verification,
        ]);
    }

    // Admin endpoints

    public function adminIndex(Request $request)
    {
        $query = StudentVerification::with(['user:id,username,email', 'reviewer:id,username'])
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return response()->json($query->paginate(20));
    }

    public function adminApprove(Request $request, $id)
    {
        $verification = StudentVerification::with('user')->findOrFail($id);

        if ($verification->status !== 'pending') {
            return response()->json(['message' => 'Yêu cầu này đã được xử lý.'], 422);
        }

        $verification->update([
            'status' => 'approved',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        $verification->user->update(['student_verified_at' => now()]);

        if ($verification->user->email) {
            try {
                Mail::to($verification->user->email)->queue(new StudentVerificationApprovedMail($verification->user));
            } catch (\Throwable $e) {
                Log::error('Failed to send student verification approved email', [
                    'verification_id' => $verification->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json(['message' => 'Đã duyệt xác minh học sinh thành công.', 'verification' => $verification]);
    }

    public function adminReject(Request $request, $id)
    {
        $request->validate(['reason' => 'required|string|max:500']);

        $verification = StudentVerification::with('user')->findOrFail($id);

        if ($verification->status !== 'pending') {
            return response()->json(['message' => 'Yêu cầu này đã được xử lý.'], 422);
        }

        $verification->update([
            'status' => 'rejected',
            'rejection_reason' => $request->reason,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        if ($verification->user->email) {
            try {
                Mail::to($verification->user->email)->queue(new StudentVerificationRejectedMail($verification->user, $request->reason));
            } catch (\Throwable $e) {
                Log::error('Failed to send student verification rejected email', [
                    'verification_id' => $verification->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json(['message' => 'Đã từ chối yêu cầu xác minh.', 'verification' => $verification]);
    }

    /**
     * Delete a verification request. An approved one loses the badge it granted,
     * so the account doesn't stay verified with no record behind it.
     */
    public function adminDestroy($id)
    {
        $verification = StudentVerification::with('user')->findOrFail($id);

        if ($verification->status === 'approved') {
            $verification->user?->update(['student_verified_at' => null]);
        }

        $verification->delete();

        return response()->json(['message' => 'Đã xóa yêu cầu xác minh.']);
    }

    public function adminRevoke(Request $request, $userId)
    {
        $user = \App\Models\User::findOrFail($userId);
        $user->update(['student_verified_at' => null]);
        StudentVerification::where('user_id', $userId)->where('status', 'approved')->update(['status' => 'rejected', 'rejection_reason' => 'Thu hồi bởi admin.', 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);

        return response()->json(['message' => 'Đã thu hồi xác minh học sinh.']);
    }
}
