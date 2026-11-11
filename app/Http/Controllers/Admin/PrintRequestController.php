<?php

namespace App\Http\Controllers\Admin;

use App\Models\PrintRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PrintRequestController
{
  public function index(Request $request): JsonResponse
  {
    $status = $request->query('status');
    $search = $request->query('search');
    $page = $request->query('page', 1);
    $perPage = $request->query('per_page', 20);

    $query = PrintRequest::with(['user', 'assignedStaff', 'file']);

    if ($status) {
      $query->where('status', $status);
    }

    if ($search) {
      $search = '%' . $search . '%';
      $query->whereHas('user', function ($q) use ($search) {
        $q->where('username', 'like', $search);
      })->orWhere('title', 'like', $search);
    }

    $requests = $query->orderBy('created_at', 'desc')
      ->paginate($perPage, ['*'], 'page', $page);

    return response()->json($requests);
  }

  public function show(PrintRequest $printRequest): JsonResponse
  {
    $printRequest->load(['user', 'assignedStaff', 'file']);
    return response()->json($printRequest);
  }

  public function updateStatus(Request $request, PrintRequest $printRequest): JsonResponse
  {
    $validated = $request->validate([
      'status' => 'required|in:approved,printing,ready,delivered,picked_up,cancelled,rejected',
      'notes' => 'nullable|string|max:500',
      'rejection_reason' => 'nullable|string|max:500',
    ]);

    $newStatus = $validated['status'];
    $currentStatus = $printRequest->status;

    $validTransitions = [
      'pending' => ['approved', 'cancelled', 'rejected'],
      'approved' => ['printing', 'cancelled', 'rejected'],
      'printing' => ['ready', 'cancelled'],
      'ready' => ['delivered', 'picked_up', 'cancelled'],
      'delivered' => [],
      'picked_up' => [],
      'cancelled' => [],
      'rejected' => [],
    ];

    if (!in_array($newStatus, $validTransitions[$currentStatus] ?? [])) {
      return response()->json([
        'message' => "Không thể chuyển từ $currentStatus sang $newStatus",
      ], 422);
    }

    $updates = ['status' => $newStatus];

    if ($newStatus === 'approved') {
      $updates['approved_at'] = now();
      $updates['assigned_staff_id'] = $request->user()->id;
    } elseif ($newStatus === 'printing') {
      $updates['printing_started_at'] = now();
    } elseif ($newStatus === 'ready') {
      $updates['ready_at'] = now();
    } elseif ($newStatus === 'delivered') {
      $updates['delivered_at'] = now();
    } elseif ($newStatus === 'picked_up') {
      $updates['picked_up_at'] = now();
    } elseif ($newStatus === 'rejected') {
      $updates['rejection_reason'] = $validated['rejection_reason'] ?? null;
    }

    $printRequest->update($updates);

    return response()->json([
      'message' => 'Cập nhật trạng thái thành công.',
      'print_request' => $printRequest,
    ]);
  }

  public function assignStaff(Request $request, PrintRequest $printRequest): JsonResponse
  {
    $validated = $request->validate([
      'assigned_staff_id' => 'required|exists:cyo_auth_accounts,id',
    ]);

    $printRequest->update($validated);

    return response()->json([
      'message' => 'Đã gán người xử lý.',
      'print_request' => $printRequest->load('assignedStaff'),
    ]);
  }

  public function destroy(PrintRequest $printRequest): JsonResponse
  {
    $printRequest->delete();

    return response()->json(['message' => 'Đã xóa yêu cầu in.']);
  }
}
