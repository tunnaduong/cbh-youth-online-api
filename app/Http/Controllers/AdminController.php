<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Topic;
use App\Models\ForumMainCategory;
use App\Models\ForumSubforum;
use App\Models\Schedule;
use App\Models\StudentViolation;
use App\Models\MonitorReport;
use App\Models\SchoolClass;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use App\Models\ForumCategory;
use App\Models\Subforum;
use App\Models\Post;

class AdminController extends Controller
{
    // User Management
    public function index()
    {
        return $this->usersIndex();
    }

    public function listUsers()
    {
        $users = User::paginate(10);
        return response()->json(['users' => $users], 200);
    }

    public function create()
    {
        return Inertia::render('Admin/Users/Create');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'role' => 'required|string|in:admin,student,teacher,monitor'
        ]);

        if ($validator->fails()) {
            if ($request->wantsJson()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }
            return back()->withErrors($validator)->withInput();
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role
        ]);

        if ($request->wantsJson()) {
            return response()->json(['message' => 'User created successfully', 'user' => $user], 201);
        }

        return redirect()->route('admin.users.index')->with('success', 'Người dùng đã được tạo thành công');
    }

    public function edit($id)
    {
        $user = User::findOrFail($id);
        return Inertia::render('Admin/Users/Edit', [
            'user' => $user
        ]);
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'string|max:255',
            'email' => 'string|email|max:255|unique:users,email,' . $id,
            'password' => 'string|min:8|nullable',
            'role' => 'string|in:admin,student,teacher,monitor'
        ]);

        if ($validator->fails()) {
            if ($request->wantsJson()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }
            return back()->withErrors($validator)->withInput();
        }

        if ($request->has('password')) {
            $request->merge(['password' => Hash::make($request->password)]);
        }

        $user->update($request->all());

        if ($request->wantsJson()) {
            return response()->json(['message' => 'User updated successfully', 'user' => $user], 200);
        }

        return redirect()->route('admin.users.index')->with('success', 'Người dùng đã được cập nhật thành công');
    }

    public function destroy($id)
    {
        $user = User::findOrFail($id);
        $user->delete();

        if (request()->wantsJson()) {
            return response()->json(['message' => 'User deleted successfully'], 200);
        }

        return redirect()->route('admin.users.index')->with('success', 'Người dùng đã được xóa thành công');
    }

    // Topic Management
    public function topicsIndex()
    {
        $topics = Topic::with('category', 'author')->paginate(10);
        return Inertia::render('Admin/Topics/Index', [
            'topics' => $topics
        ]);
    }

    public function listTopics()
    {
        $topics = Topic::with('category', 'author')->paginate(10);
        return response()->json(['topics' => $topics], 200);
    }

    public function createTopic()
    {
        $categories = ForumMainCategory::all();
        return Inertia::render('Admin/Topics/Create', [
            'categories' => $categories
        ]);
    }

    public function storeTopic(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'category_id' => 'required|exists:forum_main_categories,id',
            'status' => 'required|in:draft,published'
        ]);

        if ($validator->fails()) {
            if ($request->wantsJson()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }
            return back()->withErrors($validator)->withInput();
        }

        $topic = Topic::create($request->all());

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Topic created successfully', 'topic' => $topic], 201);
        }

        return redirect()->route('admin.topics.index')->with('success', 'Chủ đề đã được tạo thành công');
    }

    public function editTopic($id)
    {
        $topic = Topic::findOrFail($id);
        $categories = ForumMainCategory::all();
        return Inertia::render('Admin/Topics/Edit', [
            'topic' => $topic,
            'categories' => $categories
        ]);
    }

    // Forum Main Category Management
    public function categoriesIndex()
    {
        $categories = ForumMainCategory::withCount('topics')->paginate(10);
        return Inertia::render('Admin/Categories/Index', [
            'categories' => $categories
        ]);
    }

    public function createCategory()
    {
        return Inertia::render('Admin/Categories/Create');
    }

    public function storeCategory(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:forum_main_categories',
            'description' => 'string|nullable'
        ]);

        if ($validator->fails()) {
            if ($request->wantsJson()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }
            return back()->withErrors($validator)->withInput();
        }

        $category = ForumMainCategory::create($request->all());

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Main category created successfully', 'category' => $category], 201);
        }

        return redirect()->route('admin.categories.index')
            ->with('success', 'Danh mục chính đã được tạo thành công');
    }

    public function updateMainCategory(Request $request, $id)
    {
        $category = ForumMainCategory::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'string|max:255|unique:forum_main_categories,name,' . $id,
            'description' => 'string|nullable'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $category->update($request->all());
        return response()->json(['message' => 'Main category updated successfully', 'category' => $category], 200);
    }

    public function deleteMainCategory($id)
    {
        $category = ForumMainCategory::findOrFail($id);
        $category->delete();
        return response()->json(['message' => 'Main category deleted successfully'], 200);
    }

    // Forum Subforum Management (Legacy methods removed to fix duplication)

    public function deleteSubforum($id)
    {
        $subforum = ForumSubforum::findOrFail($id);
        $subforum->delete();
        return response()->json(['message' => 'Subforum deleted successfully'], 200);
    }

    // Schedule Management
    public function listSchedules()
    {
        $schedules = Schedule::with('class')->paginate(10);
        return response()->json(['schedules' => $schedules], 200);
    }

    public function createSchedule(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'class_id' => 'required|exists:classes,id',
            'day_of_week' => 'required|integer|between:1,7',
            'subject' => 'required|string|max:255',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $schedule = Schedule::create($request->all());
        return response()->json(['message' => 'Schedule created successfully', 'schedule' => $schedule], 201);
    }

    public function deleteSchedule($id)
    {
        $schedule = Schedule::findOrFail($id);
        $schedule->delete();
        return response()->json(['message' => 'Schedule deleted successfully'], 200);
    }

    // Student Violation Management
    public function violationsIndex()
    {
        $violations = StudentViolation::with(['student', 'reporter'])
            ->orderBy('mistake_type')
            ->orderBy('point_penalty')
            ->paginate(10);

        $students = User::where('role', 'student')->get();
        $teachers = User::whereIn('role', ['teacher', 'admin'])->get();

        return Inertia::render('Admin/Violations/Index', [
            'violations' => $violations,
            'mistakeTypes' => StudentViolation::MISTAKE_TYPES,
            'students' => $students,
            'teachers' => $teachers
        ]);
    }

    public function listViolations()
    {
        $violations = StudentViolation::with(['student', 'reporter'])
            ->orderBy('mistake_type')
            ->orderBy('point_penalty')
            ->paginate(10);

        return response()->json(['violations' => $violations], 200);
    }

    public function createViolation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'student_id' => 'required|exists:users,id',
            'violation_type' => 'required|string|max:255',
            'description' => 'required|string',
            'date' => 'required|date',
            'severity' => 'required|in:minor,moderate,major'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $violation = StudentViolation::create($request->all());
        return response()->json(['message' => 'Violation record created successfully', 'violation' => $violation], 201);
    }

    public function deleteViolation($id)
    {
        $violation = StudentViolation::findOrFail($id);
        $violation->delete();
        return response()->json(['message' => 'Violation record deleted successfully'], 200);
    }

    public function storeViolation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'description' => 'required|string',
            'mistake_type' => 'required|string|in:' . implode(',', StudentViolation::MISTAKE_TYPES),
            'point_penalty' => 'required|integer|min:-50|max:0',
            'student_id' => 'required|exists:cyo_auth_accounts,id',
            'reporter_id' => 'required|exists:cyo_auth_accounts,id'
        ]);

        if ($validator->fails()) {
            if ($request->wantsJson()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }
            return back()->withErrors($validator)->withInput();
        }

        $violation = StudentViolation::create($request->all());

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Thêm vi phạm thành công', 'violation' => $violation], 201);
        }

        return redirect()->route('admin.violations.index')
            ->with('success', 'Thêm vi phạm thành công');
    }

    public function updateViolation(Request $request, $id)
    {
        $violation = StudentViolation::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'description' => 'string',
            'mistake_type' => 'string|in:' . implode(',', StudentViolation::MISTAKE_TYPES),
            'point_penalty' => 'integer|min:-50|max:0',
            'student_id' => 'exists:cyo_auth_accounts,id',
            'reporter_id' => 'exists:cyo_auth_accounts,id'
        ]);

        if ($validator->fails()) {
            if ($request->wantsJson()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }
            return back()->withErrors($validator)->withInput();
        }

        $violation->update($request->all());

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Cập nhật vi phạm thành công', 'violation' => $violation]);
        }

        return redirect()->route('admin.violations.index')
            ->with('success', 'Cập nhật vi phạm thành công');
    }

    // Monitor Reports Management
    public function listMonitorReports()
    {
        $reports = MonitorReport::with(['monitor', 'class', 'violation'])
            ->latest()
            ->paginate(10);
        return response()->json(['reports' => $reports], 200);
    }

    public function monitorReportsIndex()
    {
        $reports = MonitorReport::with(['monitor', 'class', 'violation'])
            ->latest()
            ->paginate(10);

        $classes = SchoolClass::all();
        $monitors = User::where('role', 'monitor')->get();

        return Inertia::render('Admin/MonitorReports/Index', [
            'reports' => $reports,
            'classes' => $classes,
            'monitors' => $monitors
        ]);
    }

    public function storeMonitorReport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'volunteer_id' => 'required|exists:cyo_auth_accounts,id',
            'class_id' => 'required|exists:cyo_school_classes,id',
            'shift' => 'required|integer|in:1,2',
            'cleanliness' => 'required|boolean',
            'uniform' => 'required|boolean',
            'discipline' => 'required|boolean',
            'absent' => 'nullable|integer',
            'mistake_id' => 'nullable|exists:cyo_school_mistake_list,id',
            'note' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            if ($request->wantsJson()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }
            return back()->withErrors($validator)->withInput();
        }

        $report = MonitorReport::create($request->all());

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Tạo báo cáo xung kích thành công', 'report' => $report], 201);
        }

        return redirect()->route('admin.monitor-reports.index')
            ->with('success', 'Tạo báo cáo xung kích thành công');
    }

    public function updateMonitorReport(Request $request, $id)
    {
        $report = MonitorReport::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'volunteer_id' => 'exists:cyo_auth_accounts,id',
            'class_id' => 'exists:cyo_school_classes,id',
            'shift' => 'integer|in:1,2',
            'cleanliness' => 'boolean',
            'uniform' => 'boolean',
            'discipline' => 'boolean',
            'absent' => 'nullable|integer',
            'mistake_id' => 'nullable|exists:cyo_school_mistake_list,id',
            'note' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            if ($request->wantsJson()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }
            return back()->withErrors($validator)->withInput();
        }

        $report->update($request->all());

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Cập nhật báo cáo xung kích thành công', 'report' => $report]);
        }

        return redirect()->route('admin.monitor-reports.index')
            ->with('success', 'Cập nhật báo cáo xung kích thành công');
    }


    public function createClass()
    {
        $teachers = User::where('role', 'teacher')->get();
        return Inertia::render('Admin/Classes/Create', [
            'teachers' => $teachers
        ]);
    }

    public function storeClass(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'grade_level' => 'required|integer|between:10,12',
            'main_teacher_id' => 'required|exists:users,id',
            'student_count' => 'required|integer|min:0',
            'school_year' => 'required|string',
            'room_number' => 'required|string|max:10'
        ]);

        if ($validator->fails()) {
            if ($request->wantsJson()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }
            return back()->withErrors($validator)->withInput();
        }

        $class = SchoolClass::create($request->all());

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Tạo lớp học thành công', 'class' => $class], 201);
        }

        return redirect()->route('admin.classes.index')
            ->with('success', 'Tạo lớp học thành công');
    }

    // Quản lý thời khóa biểu
    public function schedulesIndex()
    {
        $schedules = Schedule::with(['class', 'teacher'])
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->paginate(10);

        $classes = SchoolClass::all();
        $teachers = User::where('role', 'teacher')->get();

        return Inertia::render('Admin/Schedules/Index', [
            'schedules' => $schedules,
            'classes' => $classes,
            'teachers' => $teachers
        ]);
    }

    public function storeSchedule(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'class_id' => 'required|exists:cyo_school_classes,id',
            'subject' => 'required|string|max:255',
            'teacher_id' => 'required|exists:users,id',
            'day_of_week' => 'required|in:Monday,Tuesday,Wednesday,Thursday,Friday',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'room_number' => 'required|string|max:10',
            'semester' => 'required|integer|in:1,2',
            'school_year' => 'required|string',
            'notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            if ($request->wantsJson()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }
            return back()->withErrors($validator)->withInput();
        }

        $schedule = Schedule::create($request->all());

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Tạo thời khóa biểu thành công', 'schedule' => $schedule], 201);
        }

        return redirect()->route('admin.schedules.index')
            ->with('success', 'Tạo thời khóa biểu thành công');
    }

    // Các phương thức cập nhật
    public function updateClass(Request $request, $id)
    {
        $class = SchoolClass::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'string|max:255',
            'grade_level' => 'integer|between:10,12',
            'main_teacher_id' => 'exists:users,id',
            'student_count' => 'integer|min:0',
            'school_year' => 'string',
            'room_number' => 'string|max:10'
        ]);

        if ($validator->fails()) {
            if ($request->wantsJson()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }
            return back()->withErrors($validator)->withInput();
        }

        $class->update($request->all());

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Cập nhật lớp học thành công', 'class' => $class]);
        }

        return redirect()->route('admin.classes.index')
            ->with('success', 'Cập nhật lớp học thành công');
    }

    public function updateSchedule(Request $request, $id)
    {
        $schedule = Schedule::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'class_id' => 'exists:cyo_school_classes,id',
            'subject' => 'string|max:255',
            'teacher_id' => 'exists:users,id',
            'day_of_week' => 'in:Monday,Tuesday,Wednesday,Thursday,Friday',
            'start_time' => 'date_format:H:i',
            'end_time' => 'date_format:H:i|after:start_time',
            'room_number' => 'string|max:10',
            'semester' => 'integer|in:1,2',
            'school_year' => 'string',
            'notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            if ($request->wantsJson()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }
            return back()->withErrors($validator)->withInput();
        }

        $schedule->update($request->all());

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Cập nhật thời khóa biểu thành công', 'schedule' => $schedule]);
        }

        return redirect()->route('admin.schedules.index')
            ->with('success', 'Cập nhật thời khóa biểu thành công');
    }

    // Các phương thức xóa
    public function destroyClass($id)
    {
        $class = SchoolClass::findOrFail($id);
        $class->delete();

        if (request()->wantsJson()) {
            return response()->json(['message' => 'Xóa lớp học thành công']);
        }

        return redirect()->route('admin.classes.index')
            ->with('success', 'Xóa lớp học thành công');
    }

    public function destroySchedule($id)
    {
        $schedule = Schedule::findOrFail($id);
        $schedule->delete();

        if (request()->wantsJson()) {
            return response()->json(['message' => 'Xóa thời khóa biểu thành công']);
        }

        return redirect()->route('admin.schedules.index')
            ->with('success', 'Xóa thời khóa biểu thành công');
    }

    public function destroyViolation($id)
    {
        $violation = StudentViolation::findOrFail($id);
        $violation->delete();

        if (request()->wantsJson()) {
            return response()->json(['message' => 'Xóa vi phạm thành công']);
        }

        return redirect()->route('admin.violations.index')
            ->with('success', 'Xóa vi phạm thành công');
    }

    public function destroyMonitorReport($id)
    {
        $report = MonitorReport::findOrFail($id);
        $report->delete();

        if (request()->wantsJson()) {
            return response()->json(['message' => 'Xóa báo cáo xung kích thành công']);
        }

        return redirect()->route('admin.monitor-reports.index')
            ->with('success', 'Xóa báo cáo xung kích thành công');
    }

    // Forum Category Management
    public function forumCategoriesIndex()
    {
        $categories = ForumCategory::withCount('subforums')
            ->withCount('posts')
            ->ordered()
            ->paginate(10);

        return Inertia::render('Admin/Forum/Categories/Index', [
            'categories' => $categories
        ]);
    }

    public function listForumCategories()
    {
        $categories = ForumCategory::withCount('subforums')
            ->withCount('posts')
            ->ordered()
            ->paginate(10);

        return response()->json(['categories' => $categories], 200);
    }

    public function createForumCategory()
    {
        return Inertia::render('Admin/Forum/Categories/Create');
    }

    public function storeForumCategory(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:cyo_forum_categories',
            'description' => 'nullable|string',
            'order' => 'integer|min:0',
            'is_active' => 'boolean'
        ]);

        if ($validator->fails()) {
            if ($request->wantsJson()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }
            return back()->withErrors($validator)->withInput();
        }

        $category = ForumCategory::create($request->all());

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Tạo danh mục thành công', 'category' => $category], 201);
        }

        return redirect()->route('admin.forum-categories.index')
            ->with('success', 'Tạo danh mục thành công');
    }

    public function editForumCategory($id)
    {
        $category = ForumCategory::findOrFail($id);
        return Inertia::render('Admin/Forum/Categories/Edit', [
            'category' => $category
        ]);
    }

    public function updateForumCategory(Request $request, $id)
    {
        $category = ForumCategory::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'string|max:255',
            'slug' => 'string|max:255|unique:cyo_forum_categories,slug,' . $id,
            'description' => 'nullable|string',
            'order' => 'integer|min:0',
            'is_active' => 'boolean'
        ]);

        if ($validator->fails()) {
            if ($request->wantsJson()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }
            return back()->withErrors($validator)->withInput();
        }

        $category->update($request->all());

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Cập nhật danh mục thành công', 'category' => $category]);
        }

        return redirect()->route('admin.forum-categories.index')
            ->with('success', 'Cập nhật danh mục thành công');
    }

    public function destroyForumCategory($id)
    {
        $category = ForumCategory::findOrFail($id);
        $category->delete();

        if (request()->wantsJson()) {
            return response()->json(['message' => 'Xóa danh mục thành công']);
        }

        return redirect()->route('admin.forum-categories.index')
            ->with('success', 'Xóa danh mục thành công');
    }

    // User Management
    public function usersIndex()
    {
        $users = User::paginate(10);
        return Inertia::render('Admin/Users/Index', [
            'users' => $users
        ]);
    }

    public function createUser()
    {
        return Inertia::render('Admin/Users/Create');
    }

    public function storeUser(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string|max:255|unique:cyo_auth_accounts',
            'email' => 'required|string|email|max:255|unique:cyo_auth_accounts',
            'password' => 'required|string|min:8',
            'role' => 'required|string|in:user,student,teacher,volunteer,admin'
        ]);

        if ($validator->fails()) {
            if ($request->wantsJson()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }
            return back()->withErrors($validator)->withInput();
        }

        $user = User::create([
            'username' => $request->username,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role
        ]);

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Tạo người dùng thành công', 'user' => $user], 201);
        }

        return redirect()->route('admin.users.index')
            ->with('success', 'Tạo người dùng thành công');
    }

    public function editUser($id)
    {
        $user = User::findOrFail($id);
        return Inertia::render('Admin/Users/Edit', [
            'user' => $user
        ]);
    }

    public function updateUser(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'username' => 'string|max:255|unique:cyo_auth_accounts,username,' . $id,
            'email' => 'string|email|max:255|unique:cyo_auth_accounts,email,' . $id,
            'password' => 'nullable|string|min:8',
            'role' => 'string|in:user,student,teacher,volunteer,admin'
        ]);

        if ($validator->fails()) {
            if ($request->wantsJson()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }
            return back()->withErrors($validator)->withInput();
        }

        $data = $request->except('password');
        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Cập nhật người dùng thành công', 'user' => $user]);
        }

        return redirect()->route('admin.users.index')
            ->with('success', 'Cập nhật người dùng thành công');
    }

    public function destroyUser($id)
    {
        $user = User::findOrFail($id);
        $user->delete();

        if (request()->wantsJson()) {
            return response()->json(['message' => 'Xóa người dùng thành công']);
        }

        return redirect()->route('admin.users.index')
            ->with('success', 'Xóa người dùng thành công');
    }

    public function userStats()
    {
        $stats = [
            'total' => User::count(),
            'admins' => User::where('role', 'admin')->count(),
            'teachers' => User::where('role', 'teacher')->count(),
            'students' => User::where('role', 'student')->count(),
            'volunteers' => User::where('role', 'volunteer')->count(),
            'users' => User::where('role', 'user')->count(),
        ];

        return response()->json(['stats' => $stats], 200);
    }

    // Class Management
    public function classesIndex()
    {
        $classes = SchoolClass::with('mainTeacher')
            ->withCount('students')
            ->paginate(10);

        return Inertia::render('Admin/Classes/Index', [
            'classes' => $classes
        ]);
    }

    public function listClasses()
    {
        $classes = SchoolClass::with('mainTeacher')
            ->select('*')
            ->addSelect(DB::raw('student_count as students_count'))
            ->paginate(10);

        return response()->json(['classes' => $classes], 200);
    }

    // Subforum Management
    public function subforumsIndex()
    {
        $subforums = Subforum::with(['category', 'moderator'])
            ->withCount('posts')
            ->ordered()
            ->paginate(10);

        $categories = ForumCategory::active()->ordered()->get();
        $moderators = User::whereIn('role', ['admin', 'moderator'])->get();

        return Inertia::render('Admin/Forum/Subforums/Index', [
            'subforums' => $subforums,
            'categories' => $categories,
            'moderators' => $moderators
        ]);
    }

    public function listSubforums()
    {
        $subforums = Subforum::with(['category', 'moderator'])
            ->withCount('posts')
            ->ordered()
            ->paginate(10);

        return response()->json(['subforums' => $subforums], 200);
    }

    public function createSubforum()
    {
        $categories = ForumCategory::active()->ordered()->get();
        $moderators = User::whereIn('role', ['admin', 'moderator'])->get();

        return Inertia::render('Admin/Forum/Subforums/Create', [
            'categories' => $categories,
            'moderators' => $moderators
        ]);
    }

    public function storeSubforum(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:cyo_forum_subforums',
            'description' => 'nullable|string',
            'category_id' => 'required|exists:cyo_forum_categories,id',
            'order' => 'integer|min:0',
            'is_active' => 'boolean',
            'moderator_id' => 'nullable|exists:cyo_auth_accounts,id'
        ]);

        if ($validator->fails()) {
            if ($request->wantsJson()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }
            return back()->withErrors($validator)->withInput();
        }

        $subforum = Subforum::create($request->all());

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Tạo diễn đàn con thành công', 'subforum' => $subforum], 201);
        }

        return redirect()->route('admin.subforums.index')
            ->with('success', 'Tạo diễn đàn con thành công');
    }

    public function editSubforum($id)
    {
        $subforum = Subforum::findOrFail($id);
        $categories = ForumCategory::active()->ordered()->get();
        $moderators = User::whereIn('role', ['admin', 'moderator'])->get();

        return Inertia::render('Admin/Forum/Subforums/Edit', [
            'subforum' => $subforum,
            'categories' => $categories,
            'moderators' => $moderators
        ]);
    }

    public function updateSubforum(Request $request, $id)
    {
        $subforum = Subforum::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'string|max:255',
            'slug' => 'string|max:255|unique:cyo_forum_subforums,slug,' . $id,
            'description' => 'nullable|string',
            'category_id' => 'exists:cyo_forum_categories,id',
            'order' => 'integer|min:0',
            'is_active' => 'boolean',
            'moderator_id' => 'nullable|exists:cyo_auth_accounts,id'
        ]);

        if ($validator->fails()) {
            if ($request->wantsJson()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }
            return back()->withErrors($validator)->withInput();
        }

        $subforum->update($request->all());

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Cập nhật diễn đàn con thành công', 'subforum' => $subforum]);
        }

        return redirect()->route('admin.subforums.index')
            ->with('success', 'Cập nhật diễn đàn con thành công');
    }

    public function destroySubforum($id)
    {
        $subforum = Subforum::findOrFail($id);
        $subforum->delete();

        if (request()->wantsJson()) {
            return response()->json(['message' => 'Xóa diễn đàn con thành công']);
        }

        return redirect()->route('admin.subforums.index')
            ->with('success', 'Xóa diễn đàn con thành công');
    }

    // Post Management
    public function postsIndex()
    {
        $posts = Post::with(['author', 'subforum.category'])
            ->withCount('replies')
            ->original()
            ->latest()
            ->paginate(10);

        return Inertia::render('Admin/Forum/Posts/Index', [
            'posts' => $posts
        ]);
    }

    public function listPosts()
    {
        $posts = Post::with(['author', 'subforum.category'])
            ->withCount('replies')
            ->original()
            ->latest()
            ->paginate(10);

        return response()->json(['posts' => $posts], 200);
    }

    public function createPost()
    {
        $subforums = Subforum::active()->ordered()->get();
        return Inertia::render('Admin/Forum/Posts/Create', [
            'subforums' => $subforums
        ]);
    }

    public function storePost(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'slug' => 'required|string|max:255',
            'content' => 'required|string',
            'subforum_id' => 'required|exists:cyo_forum_subforums,id',
            'is_pinned' => 'boolean',
            'is_locked' => 'boolean'
        ]);

        if ($validator->fails()) {
            if ($request->wantsJson()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }
            return back()->withErrors($validator)->withInput();
        }

        $post = new Post($request->all());
        $post->author_id = auth()->id();
        $post->save();

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Tạo bài viết thành công', 'post' => $post], 201);
        }

        return redirect()->route('admin.posts.index')
            ->with('success', 'Tạo bài viết thành công');
    }

    public function editPost($id)
    {
        $post = Post::findOrFail($id);
        $subforums = Subforum::active()->ordered()->get();

        return Inertia::render('Admin/Forum/Posts/Edit', [
            'post' => $post,
            'subforums' => $subforums
        ]);
    }

    public function updatePost(Request $request, $id)
    {
        $post = Post::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'title' => 'string|max:255',
            'slug' => 'string|max:255',
            'content' => 'string',
            'subforum_id' => 'exists:cyo_forum_subforums,id',
            'is_pinned' => 'boolean',
            'is_locked' => 'boolean'
        ]);

        if ($validator->fails()) {
            if ($request->wantsJson()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }
            return back()->withErrors($validator)->withInput();
        }

        $post->update($request->all());

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Cập nhật bài viết thành công', 'post' => $post]);
        }

        return redirect()->route('admin.posts.index')
            ->with('success', 'Cập nhật bài viết thành công');
    }

    public function destroyPost($id)
    {
        $post = Post::findOrFail($id);
        $post->delete();

        if (request()->wantsJson()) {
            return response()->json(['message' => 'Xóa bài viết thành công']);
        }

        return redirect()->route('admin.posts.index')
            ->with('success', 'Xóa bài viết thành công');
    }

    public function togglePinPost($id)
    {
        $post = Post::findOrFail($id);
        $post->update(['is_pinned' => !$post->is_pinned]);

        return response()->json([
            'message' => $post->is_pinned ? 'Đã ghim bài viết' : 'Đã bỏ ghim bài viết',
            'post' => $post
        ]);
    }

    public function toggleLockPost($id)
    {
        $post = Post::findOrFail($id);
        $post->update(['is_locked' => !$post->is_locked]);

        return response()->json([
            'message' => $post->is_locked ? 'Đã khóa bài viết' : 'Đã mở khóa bài viết',
            'post' => $post
        ]);
    }
}
