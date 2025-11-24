<?php

namespace App\Http\Controllers;

use App\Models\StudyMaterialCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class StudyMaterialCategoryController extends Controller
{
  /**
   * Get all categories
   *
   * @return \Illuminate\Http\JsonResponse
   */
  public function index()
  {
    $categories = StudyMaterialCategory::orderBy('order', 'asc')
      ->orderBy('name', 'asc')
      ->get();

    return response()->json($categories);
  }

  /**
   * Get single category
   *
   * @param int $id
   * @return \Illuminate\Http\JsonResponse
   */
  public function show($id)
  {
    $category = StudyMaterialCategory::with('studyMaterials')->findOrFail($id);
    return response()->json($category);
  }

  /**
   * Create category (admin only)
   *
   * @param Request $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function store(Request $request)
  {
    $user = Auth::user();
    if (!$user || !$user->hasRole('admin')) {
      return response()->json(['message' => 'Unauthorized'], 403);
    }

    $request->validate([
      'name' => 'required|string|max:255',
      'description' => 'nullable|string',
      'order' => 'nullable|integer',
    ]);

    $category = StudyMaterialCategory::create([
      'name' => $request->name,
      'description' => $request->description,
      'slug' => Str::slug($request->name),
      'order' => $request->order ?? 0,
    ]);

    return response()->json($category, 201);
  }

  /**
   * Update category (admin only)
   *
   * @param int $id
   * @param Request $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function update($id, Request $request)
  {
    $user = Auth::user();
    if (!$user || !$user->hasRole('admin')) {
      return response()->json(['message' => 'Unauthorized'], 403);
    }

    $category = StudyMaterialCategory::findOrFail($id);

    $request->validate([
      'name' => 'sometimes|string|max:255',
      'description' => 'nullable|string',
      'order' => 'nullable|integer',
    ]);

    $updateData = $request->only(['description', 'order']);
    if ($request->has('name')) {
      $updateData['name'] = $request->name;
      $updateData['slug'] = Str::slug($request->name);
    }

    $category->update($updateData);

    return response()->json($category);
  }

  /**
   * Delete category (admin only)
   *
   * @param int $id
   * @return \Illuminate\Http\JsonResponse
   */
  public function destroy($id)
  {
    $user = Auth::user();
    if (!$user || !$user->hasRole('admin')) {
      return response()->json(['message' => 'Unauthorized'], 403);
    }

    $category = StudyMaterialCategory::findOrFail($id);
    $category->delete();

    return response()->json(['message' => 'Category deleted']);
  }
}

