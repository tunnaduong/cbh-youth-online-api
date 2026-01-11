<?php

namespace App\Http\Controllers;

use App\Models\StudyMaterial;
use App\Models\StudyMaterialRating;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StudyMaterialRatingController extends Controller
{
  /**
   * Create or update rating
   *
   * @param int $materialId
   * @param Request $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function store($materialId, Request $request)
  {
    $request->validate([
      'rating' => 'required|numeric|min:1|max:5',
      'comment' => 'nullable|string|max:1000',
    ]);

    $material = StudyMaterial::findOrFail($materialId);
    /** @var \App\Models\AuthAccount $user */
    $user = Auth::user();

    // Check if user has purchased (if not free)
    if (!$material->is_free && !$material->isPurchasedBy($user->id)) {
      return response()->json(['message' => 'Bạn cần mua tài liệu này trước khi đánh giá'], 403);
    }

    $rating = StudyMaterialRating::updateOrCreate(
      [
        'user_id' => $user->id,
        'study_material_id' => $materialId,
      ],
      [
        'rating' => $request->rating,
        'comment' => $request->comment,
      ]
    );

    // Trigger notification for the author
    NotificationService::createStudyMaterialRatedNotification($material, $user, $rating);

    return response()->json($rating->load('user.profile'), 201);
  }

  /**
   * Update rating
   *
   * @param int $id
   * @param Request $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function update($id, Request $request)
  {
    $request->validate([
      'rating' => 'sometimes|numeric|min:1|max:5',
      'comment' => 'nullable|string|max:1000',
    ]);

    $rating = StudyMaterialRating::findOrFail($id);
    /** @var \App\Models\AuthAccount $user */
    $user = Auth::user();

    if ($rating->user_id !== $user->id) {
      return response()->json(['message' => 'Không có quyền chỉnh sửa'], 403);
    }

    $rating->update($request->only(['rating', 'comment']));

    // Trigger notification for the author on update as well
    $material = StudyMaterial::find($rating->study_material_id);
    if ($material) {
      NotificationService::createStudyMaterialRatedNotification($material, $user, $rating);
    }

    return response()->json($rating->load('user.profile'));
  }

  /**
   * Delete rating
   *
   * @param int $id
   * @return \Illuminate\Http\JsonResponse
   */
  public function destroy($id)
  {
    $rating = StudyMaterialRating::findOrFail($id);
    $user = Auth::user();

    if ($rating->user_id !== $user->id && !$user->hasRole('admin')) {
      return response()->json(['message' => 'Không có quyền xóa'], 403);
    }

    $rating->delete();

    return response()->json(['message' => 'Đã xóa đánh giá']);
  }

  /**
   * Get ratings for a study material
   *
   * @param int $materialId
   * @param Request $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function getRatings($materialId, Request $request)
  {
    $material = StudyMaterial::findOrFail($materialId);

    $ratings = StudyMaterialRating::with('user.profile')
      ->where('study_material_id', $materialId)
      ->orderBy('created_at', 'desc')
      ->paginate(20);

    return response()->json($ratings);
  }
}
