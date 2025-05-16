<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ForumSubforum;
use App\Models\ForumCategory;
use App\Models\AuthAccount;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ForumSubforumController extends Controller
{
    public function index()
    {
        $subforums = ForumSubforum::with(['mainCategory', 'moderator'])
            ->withCount('topics')
            ->paginate(10);

        $categories = ForumCategory::orderBy('arrange')->get();
        $moderators = AuthAccount::role('moderator')->get();

        return Inertia::render('Admin/Forum/Subforums/Index', [
            'subforums' => $subforums,
            'categories' => $categories,
            'moderators' => $moderators
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'main_category_id' => 'required|exists:cyo_forum_categories,id',
            'moderator_id' => 'nullable|exists:users,id',
            'role_restriction' => 'nullable|string',
            'active' => 'boolean',
            'pinned' => 'boolean'
        ]);

        ForumSubforum::create($validated);

        return redirect()->route('admin.subforums.index')
            ->with('success', 'Diễn đàn con đã được tạo thành công.');
    }

    public function update(Request $request, ForumSubforum $subforum)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'main_category_id' => 'required|exists:cyo_forum_categories,id',
            'moderator_id' => 'nullable|exists:users,id',
            'role_restriction' => 'nullable|string',
            'active' => 'boolean',
            'pinned' => 'boolean'
        ]);

        $subforum->update($validated);

        return redirect()->route('admin.subforums.index')
            ->with('success', 'Diễn đàn con đã được cập nhật thành công.');
    }

    public function destroy(ForumSubforum $subforum)
    {
        $subforum->delete();

        return redirect()->route('admin.subforums.index')
            ->with('success', 'Diễn đàn con đã được xóa thành công.');
    }
}
