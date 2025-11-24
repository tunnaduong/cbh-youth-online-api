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
use App\Models\AuthAccount;

/**
 * Handles administrative tasks for the application.
 */
class AdminController extends Controller
{
  // User Management
  /**
   * Display the user management index page.
   *
   * @return \Inertia\Response
   */
  public function index()
  {
    return $this->usersIndex();
  }

  /**
   * Get a paginated list of users.
   *
   * @return \Illuminate\Http\JsonResponse
   */
  public function listUsers()
  {
    $users = User::paginate(10);
    return response()->json(['users' => $users], 200);
  }

  /**
   * Show the form for creating a new user.
   *
   * @return \Inertia\Response
   */
  public function create()
  {
    return response()->json(['message' => 'Create user form data']);
  }

  /**
   * Store a newly created user in storage.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
   */
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

  /**
   * Show the form for editing the specified user.
   *
   * @param  int  $id
   * @return \Inertia\Response
   */
  public function edit($id)
  {
    $user = User::findOrFail($id);
    return response()->json([
      'user' => $user
    ]);
  }

  /**
   * Update the specified user in storage.
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  int  $id
   * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
   */
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

  /**
   * Remove the specified user from storage.
   *
   * @param  int  $id
   * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
   */
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
  /**
   * Display the topic management index page.
   *
   * @return \Inertia\Response
   */
  public function topicsIndex()
  {
    $topics = Topic::with('category', 'author')->paginate(10);
    return response()->json([
      'topics' => $topics
    ]);
  }

  /**
   * Get a paginated list of topics.
   *
   * @return \Illuminate\Http\JsonResponse
   */
  public function listTopics()
  {
    $topics = Topic::with('category', 'author')->paginate(10);
    return response()->json(['topics' => $topics], 200);
  }

  /**
   * Show the form for creating a new topic.
   *
   * @return \Inertia\Response
   */
  public function createTopic()
  {
    $categories = ForumMainCategory::all();
    return response()->json([
      'categories' => $categories
    ]);
  }

  /**
   * Store a newly created topic in storage.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
   */
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

  /**
   * Show the form for editing the specified topic.
   *
   * @param  int  $id
   * @return \Inertia\Response
   */
  public function editTopic($id)
  {
    $topic = Topic::findOrFail($id);
    $categories = ForumMainCategory::all();
    return response()->json([
      'topic' => $topic,
      'categories' => $categories
    ]);
  }

  // Forum Main Category Management
  /**
   * Display the forum main category management index page.
   *
   * @return \Inertia\Response
   */
  public function categoriesIndex()
  {
    $categories = ForumMainCategory::withCount('topics')->paginate(10);
    return response()->json([
      'categories' => $categories
    ]);
  }

  /**
   * Show the form for creating a new forum main category.
   *
   * @return \Inertia\Response
   */
  public function createCategory()
  {
    return response()->json(['message' => 'Create category form data']);
  }

  /**
   * Store a newly created forum main category in storage.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
   */
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

  /**
   * Update the specified forum main category in storage.
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  int  $id
   * @return \Illuminate\Http\JsonResponse
   */
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

  /**
   * Remove the specified forum main category from storage.
   *
   * @param  int  $id
   * @return \Illuminate\Http\JsonResponse
   */
  public function deleteMainCategory($id)
  {
    $category = ForumMainCategory::findOrFail($id);
    $category->delete();
    return response()->json(['message' => 'Main category deleted successfully'], 200);
  }

  // Forum Subforum Management (Legacy methods removed to fix duplication)

  /**
   * Remove the specified subforum from storage.
   *
   * @param  int  $id
   * @return \Illuminate\Http\JsonResponse
   */
  public function deleteSubforum($id)
  {
    $subforum = ForumSubforum::findOrFail($id);
    $subforum->delete();
    return response()->json(['message' => 'Subforum deleted successfully'], 200);
  }

  // Schedule Management
  /**
   * Get a paginated list of schedules.
   *
   * @return \Illuminate\Http\JsonResponse
   */
  public function listSchedules()
  {
    $schedules = Schedule::with('class')->paginate(10);
    return response()->json(['schedules' => $schedules], 200);
  }

  /**
   * Create a new schedule.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse
   */
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

  /**
   * Remove the specified schedule from storage.
   *
   * @param  int  $id
   * @return \Illuminate\Http\JsonResponse
   */
  public function deleteSchedule($id)
  {
    $schedule = Schedule::findOrFail($id);
    $schedule->delete();
    return response()->json(['message' => 'Schedule deleted successfully'], 200);
  }

  // Student Violation Management
  /**
   * Display the student violation management index page.
   *
   * @return \Inertia\Response
   */
  public function violationsIndex()
  {
    $violations = StudentViolation::with(['student', 'reporter'])
      ->orderBy('mistake_type')
      ->orderBy('point_penalty')
      ->paginate(10);

    $students = User::where('role', 'student')->get();
    $teachers = User::whereIn('role', ['teacher', 'admin'])->get();

    return response()->json([
      'violations' => $violations,
      'mistakeTypes' => StudentViolation::MISTAKE_TYPES,
      'students' => $students,
      'teachers' => $teachers
    ]);
  }

  /**
   * Get a paginated list of student violations.
   *
   * @return \Illuminate\Http\JsonResponse
   */
  public function listViolations()
  {
    $violations = StudentViolation::with(['student', 'reporter'])
      ->orderBy('mistake_type')
      ->orderBy('point_penalty')
      ->paginate(10);

    return response()->json(['violations' => $violations], 200);
  }

  /**
   * Create a new student violation record.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse
   */
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

  /**
   * Remove the specified student violation from storage.
   *
   * @param  int  $id
   * @return \Illuminate\Http\JsonResponse
   */
  public function deleteViolation($id)
  {
    $violation = StudentViolation::findOrFail($id);
    $violation->delete();
    return response()->json(['message' => 'Violation record deleted successfully'], 200);
  }

  /**
   * Store a newly created student violation in storage.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
   */
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

  /**
   * Update the specified student violation in storage.
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  int  $id
   * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
   */
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
  /**
   * Get a paginated list of monitor reports.
   *
   * @return \Illuminate\Http\JsonResponse
   */
  public function listMonitorReports()
  {
    $reports = MonitorReport::with(['monitor', 'class', 'violation'])
      ->latest()
      ->paginate(10);
    return response()->json(['reports' => $reports], 200);
  }

  /**
   * Display the monitor report management index page.
   *
   * @return \Inertia\Response
   */
  public function monitorReportsIndex()
  {
    $reports = MonitorReport::with(['monitor', 'class', 'violation'])
      ->latest()
      ->paginate(10);

    $classes = SchoolClass::all();
    $monitors = User::where('role', 'monitor')->get();

    return response()->json([
      'reports' => $reports,
      'classes' => $classes,
      'monitors' => $monitors
    ]);
  }

  /**
   * Store a newly created monitor report in storage.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
   */
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

  /**
   * Update the specified monitor report in storage.
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  int  $id
   * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
   */
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


  /**
   * Show the form for creating a new class.
   *
   * @return \Inertia\Response
   */
  public function createClass()
  {
    $teachers = User::where('role', 'teacher')->get();
    return response()->json([
      'teachers' => $teachers
    ]);
  }

  /**
   * Store a newly created class in storage.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
   */
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
  /**
   * Display the schedule management index page.
   *
   * @return \Inertia\Response
   */
  public function schedulesIndex()
  {
    $schedules = Schedule::with(['class', 'teacher'])
      ->orderBy('day_of_week')
      ->orderBy('start_time')
      ->paginate(10);

    $classes = SchoolClass::all();
    $teachers = User::where('role', 'teacher')->get();

    return response()->json([
      'schedules' => $schedules,
      'classes' => $classes,
      'teachers' => $teachers
    ]);
  }

  /**
   * Store a newly created schedule in storage.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
   */
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
  /**
   * Update the specified class in storage.
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  int  $id
   * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
   */
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

  /**
   * Update the specified schedule in storage.
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  int  $id
   * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
   */
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
  /**
   * Remove the specified class from storage.
   *
   * @param  int  $id
   * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
   */
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

  /**
   * Remove the specified schedule from storage.
   *
   * @param  int  $id
   * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
   */
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

  /**
   * Remove the specified student violation from storage.
   *
   * @param  int  $id
   * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
   */
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

  /**
   * Remove the specified monitor report from storage.
   *
   * @param  int  $id
   * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
   */
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
  /**
   * Display the forum category management index page.
   *
   * @return \Inertia\Response
   */
  public function forumCategoriesIndex()
  {
    $categories = ForumCategory::withCount('subforums')
      ->withCount('topics')
      ->ordered()
      ->paginate(10);

    return response()->json([
      'categories' => $categories
    ]);
  }

  /**
   * Get a paginated list of forum categories.
   *
   * @return \Illuminate\Http\JsonResponse
   */
  public function listForumCategories()
  {
    $categories = ForumCategory::withCount('subforums')
      ->withCount('topics')
      ->ordered()
      ->paginate(10);

    return response()->json(['categories' => $categories], 200);
  }

  /**
   * Show the form for creating a new forum category.
   *
   * @return \Inertia\Response
   */
  public function createForumCategory()
  {
    return response()->json(['message' => 'Create forum category form data']);
  }

  /**
   * Store a newly created forum category in storage.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
   */
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

  /**
   * Show the form for editing the specified forum category.
   *
   * @param  int  $id
   * @return \Inertia\Response
   */
  public function editForumCategory($id)
  {
    $category = ForumCategory::findOrFail($id);
    return response()->json([
      'category' => $category
    ]);
  }

  /**
   * Update the specified forum category in storage.
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  int  $id
   * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
   */
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

  /**
   * Remove the specified forum category from storage.
   *
   * @param  int  $id
   * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
   */
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
  /**
   * Display the user management index page.
   *
   * @return \Inertia\Response
   */
  public function usersIndex()
  {
    $users = User::paginate(10);
    return response()->json([
      'users' => $users
    ]);
  }

  /**
   * Show the form for creating a new user.
   *
   * @return \Inertia\Response
   */
  public function createUser()
  {
    return response()->json(['message' => 'Create user form data']);
  }

  /**
   * Store a newly created user in storage.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
   */
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

  /**
   * Show the form for editing the specified user.
   *
   * @param  int  $id
   * @return \Inertia\Response
   */
  public function editUser($id)
  {
    $user = User::findOrFail($id);
    return response()->json([
      'user' => $user
    ]);
  }

  /**
   * Update the specified user in storage.
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  int  $id
   * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
   */
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

  /**
   * Remove the specified user from storage.
   *
   * @param  int  $id
   * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
   */
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

  /**
   * Get user statistics.
   *
   * @return \Illuminate\Http\JsonResponse
   */
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
  /**
   * Display the class management index page.
   *
   * @return \Inertia\Response
   */
  public function classesIndex()
  {
    $classes = SchoolClass::with('mainTeacher')
      ->withCount('students')
      ->paginate(10);

    return response()->json([
      'classes' => $classes
    ]);
  }

  /**
   * Get a paginated list of classes.
   *
   * @return \Illuminate\Http\JsonResponse
   */
  public function listClasses()
  {
    $classes = SchoolClass::with('mainTeacher')
      ->select('*')
      ->addSelect(DB::raw('student_count as students_count'))
      ->paginate(10);

    return response()->json(['classes' => $classes], 200);
  }

  // Subforum Management
  /**
   * Display the subforum management index page.
   *
   * @return \Inertia\Response
   */
  public function subforumsIndex()
  {
    $subforums = ForumSubforum::with(['mainCategory', 'moderator'])
      ->withCount('topics')
      ->orderBy('name')
      ->paginate(10);

    $categories = ForumCategory::active()->ordered()->get();
    $moderators = AuthAccount::whereIn('role', ['admin', 'moderator'])->get();

    return response()->json([
      'subforums' => $subforums,
      'categories' => $categories,
      'moderators' => $moderators
    ]);
  }

  /**
   * Get a paginated list of subforums.
   *
   * @return \Illuminate\Http\JsonResponse
   */
  public function listSubforums()
  {
    $subforums = ForumSubforum::with(['mainCategory', 'moderator'])
      ->withCount('topics')
      ->orderBy('name')
      ->paginate(10);

    return response()->json(['subforums' => $subforums], 200);
  }

  /**
   * Show the form for creating a new subforum.
   *
   * @return \Inertia\Response
   */
  public function createSubforum()
  {
    $categories = ForumCategory::active()->ordered()->get();
    $moderators = User::whereIn('role', ['admin', 'moderator'])->get();

    return response()->json([
      'categories' => $categories,
      'moderators' => $moderators
    ]);
  }

  /**
   * Store a newly created subforum in storage.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
   */
  public function storeSubforum(Request $request)
  {
    $validator = Validator::make($request->all(), [
      'name' => 'required|string|max:255',
      'description' => 'nullable|string',
      'main_category_id' => 'required|exists:cyo_forum_main_categories,id',
      'active' => 'boolean',
      'pinned' => 'boolean',
      'role_restriction' => 'string',
      'moderator_id' => 'nullable|exists:cyo_auth_accounts,id'
    ]);

    if ($validator->fails()) {
      if ($request->wantsJson()) {
        return response()->json(['errors' => $validator->errors()], 422);
      }
      return back()->withErrors($validator)->withInput();
    }

    $subforum = ForumSubforum::create($request->all());

    if ($request->wantsJson()) {
      return response()->json(['message' => 'Tạo diễn đàn con thành công', 'subforum' => $subforum], 201);
    }

    return redirect()->route('admin.subforums.index')
      ->with('success', 'Tạo diễn đàn con thành công');
  }

  /**
   * Show the form for editing the specified subforum.
   *
   * @param  int  $id
   * @return \Inertia\Response
   */
  public function editSubforum($id)
  {
    $subforum = ForumSubforum::findOrFail($id);
    $categories = ForumCategory::active()->ordered()->get();
    $moderators = User::whereIn('role', ['admin', 'moderator'])->get();

    return response()->json([
      'subforum' => $subforum,
      'categories' => $categories,
      'moderators' => $moderators
    ]);
  }

  /**
   * Update the specified subforum in storage.
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  int  $id
   * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
   */
  public function updateSubforum(Request $request, $id)
  {
    $subforum = ForumSubforum::findOrFail($id);

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

  /**
   * Remove the specified subforum from storage.
   *
   * @param  int  $id
   * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
   */
  public function destroySubforum($id)
  {
    $subforum = ForumSubforum::findOrFail($id);
    $subforum->delete();

    if (request()->wantsJson()) {
      return response()->json(['message' => 'Xóa diễn đàn con thành công']);
    }

    return redirect()->route('admin.subforums.index')
      ->with('success', 'Xóa diễn đàn con thành công');
  }

  // Post Management
  /**
   * Display the post management index page.
   *
   * @return \Inertia\Response
   */
  public function postsIndex()
  {
    $topics = Topic::with(['author', 'subforum'])
      ->withCount('comments')
      ->where('hidden', false)
      ->latest()
      ->paginate(10);

    return response()->json([
      'posts' => $topics
    ]);
  }

  /**
   * Get a paginated list of posts.
   *
   * @return \Illuminate\Http\JsonResponse
   */
  public function listPosts()
  {
    $topics = Topic::with(['author', 'subforum'])
      ->withCount('comments')
      ->where('hidden', false)
      ->latest()
      ->paginate(10);

    return response()->json(['posts' => $topics], 200);
  }

  /**
   * Show the form for creating a new post.
   *
   * @return \Inertia\Response
   */
  public function createPost()
  {
    $subforums = ForumSubforum::active()->ordered()->get();
    return response()->json([
      'subforums' => $subforums
    ]);
  }

  /**
   * Store a newly created post in storage.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
   */
  public function storePost(Request $request)
  {
    $validator = Validator::make($request->all(), [
      'title' => 'required|string|max:255',
      'description' => 'required|string',
      'subforum_id' => 'required|exists:cyo_forum_subforums,id',
      'pinned' => 'boolean',
      'hidden' => 'boolean'
    ]);

    if ($validator->fails()) {
      if ($request->wantsJson()) {
        return response()->json(['errors' => $validator->errors()], 422);
      }
      return back()->withErrors($validator)->withInput();
    }

    $topic = new Topic($request->all());
    $topic->user_id = auth()->id();
    $topic->save();

    if ($request->wantsJson()) {
      return response()->json(['message' => 'Tạo bài viết thành công', 'topic' => $topic], 201);
    }

    return redirect()->route('admin.posts.index')
      ->with('success', 'Tạo bài viết thành công');
  }

  /**
   * Show the form for editing the specified post.
   *
   * @param  int  $id
   * @return \Inertia\Response
   */
  public function editPost($id)
  {
    $topic = Topic::findOrFail($id);
    $subforums = ForumSubforum::active()->ordered()->get();

    return response()->json([
      'topic' => $topic,
      'subforums' => $subforums
    ]);
  }

  /**
   * Update the specified post in storage.
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  int  $id
   * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
   */
  public function updatePost(Request $request, $id)
  {
    $topic = Topic::findOrFail($id);

    $validator = Validator::make($request->all(), [
      'title' => 'string|max:255',
      'description' => 'string',
      'subforum_id' => 'exists:cyo_forum_subforums,id',
      'pinned' => 'boolean',
      'hidden' => 'boolean'
    ]);

    if ($validator->fails()) {
      if ($request->wantsJson()) {
        return response()->json(['errors' => $validator->errors()], 422);
      }
      return back()->withErrors($validator)->withInput();
    }

    $topic->update($request->all());

    if ($request->wantsJson()) {
      return response()->json(['message' => 'Cập nhật bài viết thành công', 'topic' => $topic]);
    }

    return redirect()->route('admin.posts.index')
      ->with('success', 'Cập nhật bài viết thành công');
  }

  /**
   * Remove the specified post from storage.
   *
   * @param  int  $id
   * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
   */
  public function destroyPost($id)
  {
    $topic = Topic::findOrFail($id);
    $topic->delete();

    if (request()->wantsJson()) {
      return response()->json(['message' => 'Xóa bài viết thành công']);
    }

    return redirect()->route('admin.posts.index')
      ->with('success', 'Xóa bài viết thành công');
  }

  /**
   * Toggle the pinned status of a post.
   *
   * @param  int  $id
   * @return \Illuminate\Http\JsonResponse
   */
  public function togglePinPost($id)
  {
    $topic = Topic::findOrFail($id);
    $topic->update(['pinned' => !$topic->pinned]);

    return response()->json([
      'message' => $topic->pinned ? 'Đã ghim bài viết' : 'Đã bỏ ghim bài viết',
      'topic' => $topic
    ]);
  }

  /**
   * Toggle the locked status of a post.
   *
   * @param  int  $id
   * @return \Illuminate\Http\JsonResponse
   */
  public function toggleLockPost($id)
  {
    $topic = Topic::findOrFail($id);
    $topic->update(['is_locked' => !$topic->is_locked]);

    return response()->json([
      'message' => $topic->is_locked ? 'Đã khóa bài viết' : 'Đã mở khóa bài viết',
      'topic' => $topic
    ]);
  }

  // Withdrawal Management
  /**
   * Get pending withdrawal requests
   *
   * @return \Illuminate\Http\JsonResponse
   */
  public function getPendingWithdrawals()
  {
    $withdrawals = \App\Models\WithdrawalRequest::with(['user.profile'])
      ->where('status', 'pending')
      ->orderBy('created_at', 'asc')
      ->paginate(20);

    return response()->json($withdrawals);
  }

  /**
   * Approve withdrawal request
   *
   * @param int $id
   * @param Request $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function approveWithdrawal($id, Request $request)
  {
    $withdrawal = \App\Models\WithdrawalRequest::findOrFail($id);

    if ($withdrawal->status !== 'pending') {
      return response()->json(['message' => 'Yêu cầu này đã được xử lý'], 400);
    }

    $admin = Auth::user();
    $fee = 10; // 10 points = 1.000 VND
    $totalDeduction = $withdrawal->amount + $fee;

    // Check if user has enough points
    $user = $withdrawal->user;
    if (($user->points ?? 0) < $totalDeduction) {
      return response()->json([
        'message' => 'Người dùng không đủ điểm để rút',
        'required' => $totalDeduction,
        'current' => $user->points ?? 0,
      ], 400);
    }

    \Illuminate\Support\Facades\DB::transaction(function () use ($withdrawal, $admin, $totalDeduction) {
      // Deduct points
      \App\Services\PointsService::deductPoints(
        $withdrawal->user_id,
        $totalDeduction,
        'withdrawal',
        "Rút tiền: {$withdrawal->amount} điểm (phí: 10 điểm)",
        $withdrawal->id
      );

      // Update withdrawal request
      $withdrawal->update([
        'status' => 'approved',
        'admin_id' => $admin->id,
        'admin_note' => $request->admin_note ?? null,
      ]);
    });

    return response()->json([
      'message' => 'Đã duyệt yêu cầu rút tiền',
      'withdrawal' => $withdrawal->fresh(['user.profile', 'admin']),
    ]);
  }

  /**
   * Reject withdrawal request
   *
   * @param int $id
   * @param Request $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function rejectWithdrawal($id, Request $request)
  {
    $withdrawal = \App\Models\WithdrawalRequest::findOrFail($id);

    if ($withdrawal->status !== 'pending') {
      return response()->json(['message' => 'Yêu cầu này đã được xử lý'], 400);
    }

    $admin = Auth::user();

    $withdrawal->update([
      'status' => 'rejected',
      'admin_id' => $admin->id,
      'admin_note' => $request->admin_note ?? 'Yêu cầu bị từ chối',
    ]);

    return response()->json([
      'message' => 'Đã từ chối yêu cầu rút tiền',
      'withdrawal' => $withdrawal->fresh(['user.profile', 'admin']),
    ]);
  }
}
