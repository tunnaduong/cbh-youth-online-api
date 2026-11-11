<?php

namespace App\Http\Controllers;

use App\Models\PrintRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PrintRequestController extends Controller
{
  public function index(Request $request): JsonResponse
  {
    $user = $request->user();
    $page = $request->query('page', 1);
    $perPage = $request->query('per_page', 10);

    $requests = PrintRequest::where('user_id', $user->id)
      ->orderBy('created_at', 'desc')
      ->paginate($perPage, ['*'], 'page', $page);

    return response()->json($requests);
  }

  public function store(Request $request): JsonResponse
  {
    $validated = $request->validate([
      'title' => 'required|string|max:255',
      'description' => 'nullable|string|max:1000',
      'file_id' => 'required|integer|exists:cyo_cdn_user_content,id',
      'pages_count' => 'required|integer|min:1|max:1000',
      'delivery_method' => 'required|in:delivery_to_class,pickup_at_office',
      'class_or_location' => 'required|string|max:255',
      'notes' => 'nullable|string|max:500',
    ]);

    $printRequest = PrintRequest::create([
      'user_id' => $request->user()->id,
      ...$validated,
      'status' => 'pending',
    ]);

    return response()->json($printRequest, 201);
  }

  public function show(PrintRequest $printRequest): JsonResponse
  {
    if ($printRequest->user_id !== auth()->id() && !auth()->user()?->isStaff()) {
      return response()->json(['message' => 'Không có quyền truy cập.'], 403);
    }

    return response()->json($printRequest);
  }

  public function update(Request $request, PrintRequest $printRequest): JsonResponse
  {
    if ($printRequest->user_id !== $request->user()->id) {
      return response()->json(['message' => 'Không có quyền cập nhật.'], 403);
    }

    $validated = $request->validate([
      'title' => 'sometimes|string|max:255',
      'description' => 'nullable|string|max:1000',
      'pages_count' => 'sometimes|integer|min:1|max:1000',
      'delivery_method' => 'sometimes|in:delivery_to_class,pickup_at_office',
      'class_or_location' => 'sometimes|string|max:255',
      'notes' => 'nullable|string|max:500',
    ]);

    if ($printRequest->status !== 'pending') {
      return response()->json(['message' => 'Chỉ có thể chỉnh sửa yêu cầu chưa được xử lý.'], 422);
    }

    $printRequest->update($validated);

    return response()->json($printRequest);
  }

  public function cancel(PrintRequest $printRequest): JsonResponse
  {
    if ($printRequest->user_id !== auth()->id()) {
      return response()->json(['message' => 'Không có quyền hủy.'], 403);
    }

    if (!in_array($printRequest->status, ['pending', 'approved'])) {
      return response()->json(['message' => 'Không thể hủy yêu cầu ở trạng thái này.'], 422);
    }

    $printRequest->update(['status' => 'cancelled']);

    return response()->json(['message' => 'Đã hủy yêu cầu in.']);
  }
}
