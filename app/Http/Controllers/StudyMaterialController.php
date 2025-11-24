<?php

namespace App\Http\Controllers;

use App\Models\StudyMaterial;
use App\Models\StudyMaterialCategory;
use App\Models\StudyMaterialPurchase;
use App\Models\UserContent;
use App\Services\PointsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StudyMaterialController extends Controller
{
  /**
   * Get paginated list of study materials
   *
   * @param Request $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function index(Request $request)
  {
    $query = StudyMaterial::with(['user.profile', 'category', 'file'])
      ->where('status', 'published')
      ->orderBy('created_at', 'desc');

    // Filter by category
    if ($request->has('category_id')) {
      $query->where('category_id', $request->category_id);
    }

    // Filter by free/paid
    if ($request->has('is_free')) {
      $query->where('is_free', $request->is_free === 'true');
    }

    // Search
    if ($request->has('search')) {
      $search = $request->search;
      $query->where(function ($q) use ($search) {
        $q->where('title', 'like', "%{$search}%")
          ->orWhere('description', 'like', "%{$search}%");
      });
    }

    $materials = $query->paginate(20);

    $materials->getCollection()->transform(function ($material) use ($request) {
      $user = $request->user();
      $isPurchased = $user ? $material->isPurchasedBy($user->id) : false;

      return [
        'id' => $material->id,
        'title' => $material->title,
        'description' => $material->description,
        'category' => $material->category ? [
          'id' => $material->category->id,
          'name' => $material->category->name,
        ] : null,
        'price' => $material->price,
        'is_free' => $material->is_free,
        'download_count' => $material->download_count,
        'view_count' => $material->view_count,
        'average_rating' => round($material->average_rating, 1),
        'ratings_count' => $material->ratings_count,
        'author' => [
          'id' => $material->user->id,
          'username' => $material->user->username,
          'profile_name' => $material->user->profile->profile_name ?? null,
        ],
        'is_purchased' => $isPurchased,
        'created_at' => $material->created_at,
      ];
    });

    return response()->json($materials);
  }

  /**
   * Get study material detail
   *
   * @param int $id
   * @param Request $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function show($id, Request $request)
  {
    $material = StudyMaterial::with(['user.profile', 'category', 'file', 'ratings.user.profile'])
      ->findOrFail($id);

    if ($material->status !== 'published' && (!$request->user() || $request->user()->id !== $material->user_id)) {
      return response()->json(['message' => 'Tài liệu không tồn tại'], 404);
    }

    $user = $request->user();
    $isPurchased = $user ? $material->isPurchasedBy($user->id) : false;
    $userRating = $user ? $material->ratings()->where('user_id', $user->id)->first() : null;

    return response()->json([
      'id' => $material->id,
      'title' => $material->title,
      'description' => $material->description,
      'category' => $material->category ? [
        'id' => $material->category->id,
        'name' => $material->category->name,
      ] : null,
      'price' => $material->price,
      'is_free' => $material->is_free,
      'preview_content' => $material->preview_content,
      'download_count' => $material->download_count,
      'view_count' => $material->view_count,
      'average_rating' => round($material->average_rating, 1),
      'ratings_count' => $material->ratings_count,
      'author' => [
        'id' => $material->user->id,
        'username' => $material->user->username,
        'profile_name' => $material->user->profile->profile_name ?? null,
      ],
      'file' => $material->file ? [
        'id' => $material->file->id,
        'file_name' => $material->file->file_name,
        'file_type' => $material->file->file_type,
        'file_size' => $material->file->file_size,
      ] : null,
      'is_purchased' => $isPurchased,
      'user_rating' => $userRating ? [
        'id' => $userRating->id,
        'rating' => $userRating->rating,
        'comment' => $userRating->comment,
      ] : null,
      'created_at' => $material->created_at,
      'updated_at' => $material->updated_at,
    ]);
  }

  /**
   * Create new study material
   *
   * @param Request $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function store(Request $request)
  {
    $request->validate([
      'title' => 'required|string|max:255',
      'description' => 'nullable|string',
      'category_id' => 'nullable|exists:cyo_study_material_categories,id',
      'file_id' => 'required|exists:cyo_cdn_user_content,id',
      'price' => 'required|integer|min:0',
      'is_free' => 'boolean',
      'preview_content' => 'nullable|string',
      'status' => 'in:draft,published',
    ]);

    // If is_free is true, set price to 0
    if ($request->is_free) {
      $request->merge(['price' => 0]);
    }

    $user = Auth::user();

    // Verify file belongs to user
    $file = UserContent::where('id', $request->file_id)
      ->where('user_id', $user->id)
      ->firstOrFail();

    $material = StudyMaterial::create([
      'user_id' => $user->id,
      'title' => $request->title,
      'description' => $request->description,
      'category_id' => $request->category_id,
      'file_path' => $request->file_id,
      'price' => $request->is_free ? 0 : ($request->price ?? 0),
      'is_free' => $request->is_free ?? ($request->price == 0),
      'preview_content' => $request->preview_content,
      'status' => $request->status ?? 'published',
    ]);

    return response()->json($material->load(['user.profile', 'category', 'file']), 201);
  }

  /**
   * Update study material
   *
   * @param int $id
   * @param Request $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function update($id, Request $request)
  {
    $material = StudyMaterial::findOrFail($id);
    $user = Auth::user();

    if ($material->user_id !== $user->id && !$user->hasRole('admin')) {
      return response()->json(['message' => 'Không có quyền chỉnh sửa'], 403);
    }

    $request->validate([
      'title' => 'sometimes|string|max:255',
      'description' => 'nullable|string',
      'category_id' => 'nullable|exists:cyo_study_material_categories,id',
      'price' => 'sometimes|integer|min:0',
      'is_free' => 'boolean',
      'preview_content' => 'nullable|string',
      'status' => 'in:draft,published',
    ]);

    $material->update($request->only([
      'title',
      'description',
      'category_id',
      'price',
      'is_free',
      'preview_content',
      'status',
    ]));

    return response()->json($material->load(['user.profile', 'category', 'file']));
  }

  /**
   * Delete study material
   *
   * @param int $id
   * @return \Illuminate\Http\JsonResponse
   */
  public function destroy($id)
  {
    $material = StudyMaterial::findOrFail($id);
    $user = Auth::user();

    if ($material->user_id !== $user->id && !$user->hasRole('admin')) {
      return response()->json(['message' => 'Không có quyền xóa'], 403);
    }

    $material->delete();

    return response()->json(['message' => 'Đã xóa tài liệu']);
  }

  /**
   * Purchase study material
   *
   * @param int $id
   * @param Request $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function purchase($id, Request $request)
  {
    $material = StudyMaterial::findOrFail($id);
    $user = Auth::user();

    if ($material->is_free) {
      return response()->json(['message' => 'Tài liệu này miễn phí'], 400);
    }

    if ($material->isPurchasedBy($user->id)) {
      return response()->json(['message' => 'Bạn đã mua tài liệu này rồi'], 400);
    }

    $userPoints = $user->points ?? 0;
    if ($userPoints < $material->price) {
      return response()->json([
        'message' => 'Số điểm không đủ',
        'required' => $material->price,
        'current' => $userPoints,
      ], 400);
    }

    DB::transaction(function () use ($material, $user) {
      // Deduct points
      PointsService::deductPoints(
        $user->id,
        $material->price,
        'purchase',
        "Mua tài liệu: {$material->title}",
        $material->id
      );

      // Create purchase record
      StudyMaterialPurchase::create([
        'user_id' => $user->id,
        'study_material_id' => $material->id,
        'price_paid' => $material->price,
      ]);
    });

    return response()->json(['message' => 'Mua tài liệu thành công'], 201);
  }

  /**
   * Download study material file
   *
   * @param int $id
   * @return \Illuminate\Http\Response
   */
  public function download($id)
  {
    $material = StudyMaterial::with('file')->findOrFail($id);
    $user = Auth::user();

    // Check if free or purchased
    if (!$material->is_free && !$material->isPurchasedBy($user->id)) {
      return response()->json(['message' => 'Bạn cần mua tài liệu này trước'], 403);
    }

    if (!$material->file) {
      return response()->json(['message' => 'File không tồn tại'], 404);
    }

    // Increment download count
    $material->increment('download_count');

    $filePath = storage_path('app/public/' . $material->file->file_path);
    if (!file_exists($filePath)) {
      return response()->json(['message' => 'File không tìm thấy'], 404);
    }

    return Storage::disk('public')->download($material->file->file_path, $material->file->file_name);
  }

  /**
   * View study material (increment view count)
   *
   * @param int $id
   * @return \Illuminate\Http\JsonResponse
   */
  public function view($id)
  {
    $material = StudyMaterial::findOrFail($id);
    $material->increment('view_count');

    return response()->json(['view_count' => $material->view_count]);
  }

  /**
   * Get preview content
   *
   * @param int $id
   * @return \Illuminate\Http\JsonResponse
   */
  public function getPreview($id)
  {
    $material = StudyMaterial::findOrFail($id);

    return response()->json([
      'preview_content' => $material->preview_content,
      'is_free' => $material->is_free,
      'price' => $material->price,
    ]);
  }

  /**
   * Get user's study materials
   *
   * @param string $username
   * @param Request $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function getUserMaterials($username, Request $request)
  {
    $user = \App\Models\AuthAccount::where('username', $username)->firstOrFail();
    $currentUser = $request->user();

    $query = StudyMaterial::with(['category', 'file'])
      ->where('user_id', $user->id);

    // If viewing own materials, show all. Otherwise only published
    if (!$currentUser || $currentUser->id !== $user->id) {
      $query->where('status', 'published');
    }

    $materials = $query->orderBy('created_at', 'desc')->paginate(20);

    return response()->json($materials);
  }
}

