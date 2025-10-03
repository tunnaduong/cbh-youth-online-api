<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ForumCategory;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Handles the administration of forum categories.
 */
class ForumCategoryController extends Controller
{
    /**
     * Display a listing of the forum categories.
     *
     * @return \Inertia\Response
     */
    public function index()
    {
        $categories = ForumCategory::withCount('subforums')
            ->orderBy('arrange')
            ->paginate(10);

        return Inertia::render('Admin/Forum/Categories/Index', [
            'categories' => $categories
        ]);
    }

    /**
     * Store a newly created forum category in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'arrange' => 'required|integer|min:0',
            'role_restriction' => 'nullable|string',
            'background_image' => 'nullable|string'
        ]);

        ForumCategory::create($validated);

        return redirect()->route('admin.categories.index')
            ->with('success', 'Danh mục đã được tạo thành công.');
    }

    /**
     * Update the specified forum category in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\ForumCategory  $category
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, ForumCategory $category)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'arrange' => 'required|integer|min:0',
            'role_restriction' => 'nullable|string',
            'background_image' => 'nullable|string'
        ]);

        $category->update($validated);

        return redirect()->route('admin.categories.index')
            ->with('success', 'Danh mục đã được cập nhật thành công.');
    }

    /**
     * Remove the specified forum category from storage.
     *
     * @param  \App\Models\ForumCategory  $category
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(ForumCategory $category)
    {
        $category->delete();

        return redirect()->route('admin.categories.index')
            ->with('success', 'Danh mục đã được xóa thành công.');
    }
}
