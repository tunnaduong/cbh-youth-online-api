<?php

namespace App\Http\Controllers;

use App\Models\StudyMaterial;
use App\Models\StudyMaterialCategory;
use App\Models\StudyMaterialPurchase;
use App\Models\UserContent;
use App\Services\PointsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
    try {
      $query = StudyMaterial::with(['user.profile', 'category', 'file'])
        ->where('status', 'published');

      // Filter by category
      if ($request->has('category_id') && $request->category_id) {
        $query->where('category_id', $request->category_id);
      }

      // Filter by free/paid
      if ($request->has('is_free') && $request->is_free !== null) {
        $query->where('is_free', $request->is_free === 'true' || $request->is_free === true);
      }

      // Filter by purchased/authored
      if ($request->has('is_purchased') && $request->is_purchased === 'true' && Auth::check()) {
        $query->where(function ($q) {
          $q->whereHas('purchases', function ($sub) {
            $sub->where('user_id', Auth::id());
          })->orWhere('user_id', Auth::id());
        });
      }

      // Search
      if ($request->has('search') && $request->search) {
        $search = $request->search;
        $query->where(function ($q) use ($search) {
          $q
            ->where('title', 'like', "%{$search}%")
            ->orWhere('description', 'like', "%{$search}%");
        });
      }

      // Sorting
      $sortBy = $request->query('sort_by', 'created_at');
      $sortOrder = strtolower($request->query('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc';

      $allowedSortFields = [
        'created_at' => 'cyo_study_materials.created_at',
        'title' => 'cyo_study_materials.title',
        'price' => 'cyo_study_materials.price',
        'view_count' => 'cyo_study_materials.view_count',
        'download_count' => 'cyo_study_materials.download_count',
        'average_rating' => 'average_rating'
      ];

      if (array_key_exists($sortBy, $allowedSortFields)) {
        $column = $allowedSortFields[$sortBy];
        if ($sortBy === 'average_rating') {
          $query
            ->withAvg('ratings', 'rating')
            ->orderBy('ratings_avg_rating', $sortOrder);
        } else {
          $query->orderBy($column, $sortOrder);
        }
      } else {
        $query->orderBy('cyo_study_materials.created_at', 'desc');
      }

      // Add secondary stable sort
      $query->orderBy('cyo_study_materials.id', $sortOrder);

      $materials = $query->paginate(20);

      $materials = $materials->through(function ($material) use ($request) {
        try {
          $user = $request->user();
          $isPurchased = $user ? ($material->isPurchasedBy($user->id) || $user->id === $material->user_id) : false;

          // Calculate average rating safely
          $ratingsCount = $material->ratings()->count();
          $averageRating = $ratingsCount > 0
            ? round((float) $material->ratings()->avg('rating'), 1)
            : 0;

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
            'download_count' => $material->download_count ?? 0,
            'view_count' => $material->view_count ?? 0,
            'average_rating' => $averageRating,
            'ratings_count' => $ratingsCount,
            'author' => [
              'id' => $material->user->id,
              'username' => $material->user->username,
              'profile_name' => $material->user->profile->profile_name ?? null,
            ],
            'is_purchased' => $isPurchased,
            'created_at' => $material->created_at,
          ];
        } catch (\Exception $e) {
          Log::error('Error transforming material: ' . $e->getMessage(), [
            'material_id' => $material->id ?? null,
            'trace' => $e->getTraceAsString()
          ]);
          return null;
        }
      });

      return response()->json($materials);
    } catch (\Exception $e) {
      Log::error('Error loading study materials: ' . $e->getMessage(), [
        'trace' => $e->getTraceAsString()
      ]);
      return response()->json([
        'error' => 'Failed to load materials',
        'message' => $e->getMessage()
      ], 500);
    }
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
    $isPurchased = $user ? ($material->isPurchasedBy($user->id) || $user->id === $material->user_id) : false;
    $userRating = $user ? $material->ratings()->where('user_id', $user->id)->first() : null;

    // Calculate average rating safely
    $ratingsCount = $material->ratings()->count();
    $averageRating = $ratingsCount > 0
      ? round($material->ratings()->avg('rating'), 1)
      : 0;

    // Generate one-time preview key with very short TTL (20 seconds) - enough for MS Office to fetch
    $previewKey = Str::random(40);
    Cache::put('doc_preview_' . $previewKey, $material->id, now()->addSeconds(20));

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
      'preview_key' => $previewKey,
      'preview_content' => $material->preview_content,
      'download_count' => $material->download_count ?? 0,
      'view_count' => $material->view_count ?? 0,
      'average_rating' => $averageRating,
      'ratings_count' => $ratingsCount,
      'author' => [
        'id' => $material->user->id,
        'username' => $material->user->username,
        'profile_name' => $material->user->profile->profile_name ?? null,
      ],
      'file' => $material->file ? [
        'id' => $material->file->id,
        'file_name' => $material->file->file_name,
        'file_path' => $material->file->file_path,
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
    /** @var \App\Models\AuthAccount $user */
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
      // Deduct points from buyer
      PointsService::deductPoints(
        $user->id,
        $material->price,
        'purchase',
        "Mua tài liệu: {$material->title}",
        $material->id
      );

      // Add points to author
      PointsService::addPoints(
        $material->user_id,
        $material->price,
        'earning',
        "Người dùng {$user->username} mua tài liệu: {$material->title}",
        $material->id
      );

      // Create purchase record
      StudyMaterialPurchase::create([
        'user_id' => $user->id,
        'study_material_id' => $material->id,
        'price_paid' => $material->price,
      ]);
    });

    // Notify author
    \App\Services\NotificationService::createStudyMaterialPurchasedNotification($material, $user);

    return response()->json(['message' => 'Mua tài liệu thành công'], 201);
  }

  /**
   * Download study material file
   *
   * @param int $id
   * @return \Illuminate\Http\Response|\Symfony\Component\HttpFoundation\BinaryFileResponse|\Illuminate\Http\JsonResponse
   */
  public function download($id)
  {
    $material = StudyMaterial::with('file')->findOrFail($id);
    $user = Auth::user();

    // Check if free, purchased, or owner/admin
    $isOwner = $user && $material->user_id === $user->id;
    $isAdmin = $user && $user->hasRole('admin');

    if (!$material->is_free && !$material->isPurchasedBy($user->id) && !$isOwner && !$isAdmin) {
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

  /**
   * View document for preview mechanism (one-time/temporary link)
   *
   * @param Request $request
   * @return \Symfony\Component\HttpFoundation\BinaryFileResponse|\Illuminate\Http\JsonResponse
   */
  public function viewDocument(Request $request)
  {
    $id = $request->input('id');
    $key = $request->input('key');

    if (!$id || !$key) {
      return response()->json(['message' => 'Missing parameters'], 400);
    }

    $cachedId = Cache::get('doc_preview_' . $key);

    if (!$cachedId || $cachedId != $id) {
      return response()->json(['message' => 'Liên kết đã hết hạn hoặc không hợp lệ'], 403);
    }

    $material = StudyMaterial::with('file')->find($id);

    if (!$material || !$material->file) {
      return response()->json(['message' => 'File not found'], 404);
    }

    $filePath = $material->file->file_path;
    $localPath = storage_path('app/public/' . $filePath);

    if (!file_exists($localPath)) {
      return response()->json(['message' => 'File missing on server'], 404);
    }

    return response()->file($localPath, [
      'Content-Type' => $material->file->file_type,
      'Content-Disposition' => 'inline; filename="' . $material->file->file_name . '"',
      'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
      'Pragma' => 'no-cache',
    ]);
  }
}
