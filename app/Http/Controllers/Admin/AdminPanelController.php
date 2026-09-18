<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendAdminBroadcast;
use App\Models\AdminBroadcast;
use App\Models\AuthAccount;
use App\Models\ExpoPushToken;
use App\Models\NotificationSubscription;
use App\Models\PendingDeposit;
use App\Models\ShopCategory;
use App\Models\ShopOrder;
use App\Models\ShopProduct;
use App\Models\StudyMaterial;
use App\Models\Topic;
use App\Models\TopicComment;
use App\Models\UserReport;
use App\Models\WithdrawalRequest;
use App\Services\PointsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * JSON endpoints backing the Next.js admin panel (/admin).
 * All routes are behind auth:sanctum + role:admin.
 */
class AdminPanelController extends Controller
{
  private function perPage(Request $request): int
  {
    return min(max((int) $request->input('per_page', 20), 1), 100);
  }

  /**
   * Apply a "search" filter over the given columns plus the owner's username.
   */
  private function applySearch($query, Request $request, array $columns, bool $withUser = true)
  {
    $search = trim((string) $request->input('search', ''));
    if ($search === '') {
      return $query;
    }

    return $query->where(function ($q) use ($search, $columns, $withUser) {
      foreach ($columns as $col) {
        $q->orWhere($col, 'like', "%{$search}%");
      }
      if (ctype_digit($search)) {
        $q->orWhere($q->getModel()->getTable() . '.id', (int) $search);
      }
      if ($withUser) {
        $q->orWhereHas('user', fn($u) => $u->where('username', 'like', "%{$search}%"));
      }
    });
  }

  // ---------------------------------------------------------------- Overview

  public function overview()
  {
    return response()->json([
      'users' => AuthAccount::count(),
      'banned_users' => AuthAccount::whereNotNull('banned_at')->count(),
      'topics' => Topic::count(),
      'hidden_topics' => Topic::where('hidden', 1)->count(),
      'comments' => TopicComment::count(),
      'pending_reports' => UserReport::where('status', 'pending')->count(),
      'pending_deposits' => PendingDeposit::where('status', 'pending')->count(),
      'pending_withdrawals' => WithdrawalRequest::where('status', 'pending')->count(),
      'pending_orders' => ShopOrder::where('status', 'pending')->count(),
      'study_materials' => StudyMaterial::count(),
      'daily' => $this->dailySeries(14),
    ]);
  }

  /**
   * New users / topics / comments per day for the last $days days (oldest first).
   */
  private function dailySeries(int $days): array
  {
    $from = now()->subDays($days - 1)->startOfDay();
    $count = fn($model) => $model::where('created_at', '>=', $from)
      ->selectRaw('DATE(created_at) as d, COUNT(*) as c')
      ->groupBy('d')
      ->pluck('c', 'd');

    $users = $count(AuthAccount::class);
    $topics = $count(Topic::class);
    $comments = $count(TopicComment::class);

    $series = [];
    for ($i = 0; $i < $days; $i++) {
      $d = $from->copy()->addDays($i)->toDateString();
      $series[] = [
        'date' => $d,
        'users' => (int) ($users[$d] ?? 0),
        'topics' => (int) ($topics[$d] ?? 0),
        'comments' => (int) ($comments[$d] ?? 0),
      ];
    }
    return $series;
  }

  // ---------------------------------------------------------------- Topics

  public function topics(Request $request)
  {
    $query = Topic::query()
      ->select(['id', 'subforum_id', 'user_id', 'title', 'description', 'pinned', 'hidden', 'privacy', 'anonymous', 'created_at'])
      ->with(['user:id,username', 'subforum:id,name'])
      ->withCount('comments');

    $this->applySearch($query, $request, ['title', 'description']);

    if ($request->filled('visibility')) {
      $query->where('hidden', $request->visibility === 'hidden' ? 1 : 0);
    }
    if ($request->filled('pinned')) {
      $query->where('pinned', $request->boolean('pinned'));
    }
    if ($request->filled('privacy')) {
      $query->where('privacy', $request->privacy);
    }
    if ($request->filled('subforum_id')) {
      $query->where('subforum_id', $request->subforum_id);
    }
    if ($request->filled('from_date')) {
      $query->whereDate('created_at', '>=', $request->from_date);
    }
    if ($request->filled('to_date')) {
      $query->whereDate('created_at', '<=', $request->to_date);
    }

    $topics = $query->orderByDesc('id')->paginate($this->perPage($request));
    // Keep list payload light: the model appends content/document/video accessors.
    $topics->getCollection()->each->setAppends([]);

    return response()->json($topics);
  }

  public function updateTopic(Request $request, $id)
  {
    $data = $request->validate([
      'hidden' => 'sometimes|boolean',
      'pinned' => 'sometimes|boolean',
    ]);

    $topic = Topic::findOrFail($id);
    $topic->fill([
      'hidden' => array_key_exists('hidden', $data) ? (int) $data['hidden'] : $topic->hidden,
      'pinned' => $data['pinned'] ?? $topic->pinned,
    ])->save();

    return response()->json(['message' => 'Đã cập nhật bài viết.', 'topic' => $topic->only(['id', 'hidden', 'pinned'])]);
  }

  public function deleteTopic($id)
  {
    Topic::findOrFail($id)->delete();
    return response()->json(['message' => 'Đã xóa bài viết.']);
  }

  // ---------------------------------------------------------------- Comments

  public function comments(Request $request)
  {
    $query = TopicComment::query()
      ->select(['id', 'topic_id', 'user_id', 'replying_to', 'comment', 'is_anonymous', 'created_at'])
      ->with(['user:id,username', 'topic:id,title']);

    $this->applySearch($query, $request, ['comment']);

    if ($request->filled('topic_id')) {
      $query->where('topic_id', $request->topic_id);
    }

    return response()->json($query->orderByDesc('id')->paginate($this->perPage($request)));
  }

  public function deleteComment($id)
  {
    TopicComment::findOrFail($id)->delete();
    return response()->json(['message' => 'Đã xóa bình luận.']);
  }

  // ---------------------------------------------------------------- Users

  public function users(Request $request)
  {
    $query = AuthAccount::query()
      ->select(['id', 'username', 'email', 'role', 'points', 'email_verified_at', 'last_activity', 'banned_at', 'banned_until', 'ban_reason', 'created_at'])
      ->with('profile:id,auth_account_id,profile_name')
      ->withCount('posts');

    $search = trim((string) $request->input('search', ''));
    if ($search !== '') {
      $query->where(function ($q) use ($search) {
        $q->where('username', 'like', "%{$search}%")
          ->orWhere('email', 'like', "%{$search}%")
          ->orWhereHas('profile', fn($p) => $p->where('profile_name', 'like', "%{$search}%"));
        if (ctype_digit($search)) {
          $q->orWhere('id', (int) $search);
        }
      });
    }

    if ($request->filled('role')) {
      $query->where('role', $request->role);
    }
    if ($request->filled('banned')) {
      $request->boolean('banned')
        ? $query->whereNotNull('banned_at')->where(fn($q) => $q->whereNull('banned_until')->orWhere('banned_until', '>', now()))
        : $query->where(fn($q) => $q->whereNull('banned_at')->orWhere('banned_until', '<=', now()));
    }

    return response()->json($query->orderByDesc('id')->paginate($this->perPage($request)));
  }

  public function updateUser(Request $request, $id)
  {
    $data = $request->validate([
      'role' => 'sometimes|string|in:user,student,teacher,volunteer,admin',
      'points' => 'sometimes|integer|min:0',
    ]);

    $user = AuthAccount::findOrFail($id);
    if (isset($data['role']) && $user->id === Auth::id() && $data['role'] !== 'admin') {
      return response()->json(['message' => 'Không thể tự hạ quyền của chính bạn.'], 403);
    }

    $user->fill($data)->save();

    return response()->json(['message' => 'Đã cập nhật người dùng.', 'user' => $user->only(['id', 'role', 'points'])]);
  }

  // ---------------------------------------------------------------- Pending deposits

  public function pendingDeposits(Request $request)
  {
    $query = PendingDeposit::query()->with('user:id,username');
    $this->applySearch($query, $request, ['deposit_code']);

    if ($request->filled('status')) {
      $query->where('status', $request->status);
    }

    return response()->json($query->orderByDesc('id')->paginate($this->perPage($request)));
  }

  /**
   * Manually confirm a deposit (e.g. the bank transfer arrived but the SePay webhook missed it).
   */
  public function approveDeposit($id)
  {
    $deposit = PendingDeposit::findOrFail($id);
    if ($deposit->status === 'completed') {
      return response()->json(['message' => 'Giao dịch này đã được xử lý.'], 400);
    }

    DB::transaction(function () use ($deposit) {
      PointsService::addPoints(
        $deposit->user_id,
        $deposit->expected_points,
        'deposit',
        'Nạp tiền (duyệt thủ công bởi admin): ' . number_format($deposit->amount_vnd) . " VND - {$deposit->deposit_code}",
        $deposit->id
      );
      $deposit->update(['status' => 'completed']);
    });

    return response()->json(['message' => 'Đã xác nhận nạp tiền và cộng điểm.']);
  }

  public function expireDeposit($id)
  {
    $deposit = PendingDeposit::findOrFail($id);
    if ($deposit->status !== 'pending') {
      return response()->json(['message' => 'Giao dịch này đã được xử lý.'], 400);
    }
    $deposit->update(['status' => 'expired']);
    return response()->json(['message' => 'Đã hủy yêu cầu nạp tiền.']);
  }

  // ---------------------------------------------------------------- Withdrawals

  public function withdrawals(Request $request)
  {
    $query = WithdrawalRequest::query()->with(['user:id,username', 'admin:id,username']);
    $this->applySearch($query, $request, ['bank_account', 'bank_name', 'account_holder']);

    if ($request->filled('status')) {
      $query->where('status', $request->status);
    }

    return response()->json($query->orderByDesc('id')->paginate($this->perPage($request)));
  }

  // ---------------------------------------------------------------- Shop categories

  public function shopCategories(Request $request)
  {
    $query = ShopCategory::query()->withCount('products');
    $this->applySearch($query, $request, ['name', 'slug'], false);
    return response()->json($query->orderBy('id')->paginate($this->perPage($request)));
  }

  public function saveShopCategory(Request $request, $id = null)
  {
    $data = $request->validate([
      'name' => 'required|string|max:255',
      'slug' => 'nullable|string|max:255|unique:cyo_shop_categories,slug' . ($id ? ",{$id}" : ''),
      'description' => 'nullable|string',
    ]);
    $data['slug'] = $data['slug'] ?: Str::slug($data['name']);

    $category = $id ? ShopCategory::findOrFail($id) : new ShopCategory();
    $category->fill($data)->save();

    return response()->json(['message' => 'Đã lưu danh mục.', 'category' => $category]);
  }

  public function deleteShopCategory($id)
  {
    $category = ShopCategory::withCount('products')->findOrFail($id);
    if ($category->products_count > 0) {
      return response()->json(['message' => 'Danh mục còn sản phẩm, không thể xóa.'], 400);
    }
    $category->delete();
    return response()->json(['message' => 'Đã xóa danh mục.']);
  }

  // ---------------------------------------------------------------- Shop products

  public function shopProducts(Request $request)
  {
    $query = ShopProduct::query()->with('category:id,name');
    $this->applySearch($query, $request, ['name', 'slug'], false);

    if ($request->filled('category_id')) {
      $query->where('category_id', $request->category_id);
    }
    if ($request->filled('is_active')) {
      $query->where('is_active', $request->boolean('is_active'));
    }

    return response()->json($query->orderByDesc('id')->paginate($this->perPage($request)));
  }

  public function saveShopProduct(Request $request, $id = null)
  {
    $data = $request->validate([
      'name' => 'required|string|max:255',
      'slug' => 'nullable|string|max:255|unique:cyo_shop_products,slug' . ($id ? ",{$id}" : ''),
      'description' => 'nullable|string',
      'price' => 'required|integer|min:0',
      'stock' => 'required|integer|min:0',
      'image_url' => 'nullable|string|max:255',
      'category_id' => 'required|exists:cyo_shop_categories,id',
      'is_active' => 'boolean',
    ]);
    $data['slug'] = $data['slug'] ?: Str::slug($data['name']) . '-' . Str::lower(Str::random(4));

    $product = $id ? ShopProduct::findOrFail($id) : new ShopProduct();
    $product->fill($data)->save();

    return response()->json(['message' => 'Đã lưu sản phẩm.', 'product' => $product]);
  }

  public function deleteShopProduct($id)
  {
    ShopProduct::findOrFail($id)->delete();
    return response()->json(['message' => 'Đã xóa sản phẩm.']);
  }

  // ---------------------------------------------------------------- Shop orders

  public function shopOrders(Request $request)
  {
    $query = ShopOrder::query()->with(['user:id,username', 'items.product:id,name']);
    $this->applySearch($query, $request, ['phone', 'shipping_address']);

    if ($request->filled('status')) {
      $query->where('status', $request->status);
    }

    return response()->json($query->orderByDesc('id')->paginate($this->perPage($request)));
  }

  public function updateShopOrder(Request $request, $id)
  {
    $data = $request->validate([
      'status' => 'required|string|in:pending,processing,shipped,completed,cancelled',
    ]);

    $order = ShopOrder::with('items')->findOrFail($id);
    if ($order->status === 'cancelled') {
      return response()->json(['message' => 'Đơn hàng đã hủy, không thể thay đổi.'], 400);
    }

    DB::transaction(function () use ($order, $data) {
      // Return stock when an order is cancelled.
      if ($data['status'] === 'cancelled') {
        foreach ($order->items as $item) {
          ShopProduct::withTrashed()->where('id', $item->product_id)->increment('stock', $item->quantity);
        }
      }
      $order->update(['status' => $data['status']]);
    });

    return response()->json(['message' => 'Đã cập nhật đơn hàng.']);
  }

  // ---------------------------------------------------------------- Study materials

  public function studyMaterials(Request $request)
  {
    $query = StudyMaterial::query()->with(['user:id,username', 'category:id,name']);
    $this->applySearch($query, $request, ['title', 'description']);

    if ($request->filled('status')) {
      $query->where('status', $request->status);
    }
    if ($request->filled('category_id')) {
      $query->where('category_id', $request->category_id);
    }
    if ($request->filled('is_free')) {
      $query->where('is_free', $request->boolean('is_free'));
    }

    return response()->json($query->orderByDesc('id')->paginate($this->perPage($request)));
  }

  public function updateStudyMaterial(Request $request, $id)
  {
    $data = $request->validate([
      'status' => 'required|string|in:draft,published',
    ]);
    StudyMaterial::findOrFail($id)->update($data);
    return response()->json(['message' => 'Đã cập nhật tài liệu.']);
  }

  public function deleteStudyMaterial($id)
  {
    StudyMaterial::findOrFail($id)->delete();
    return response()->json(['message' => 'Đã xóa tài liệu.']);
  }

  // ---------------------------------------------------------------- Push broadcasts

  private function audienceRules(): array
  {
    return [
      'audience' => 'required|in:all,role,users',
      'role' => 'required_if:audience,role|nullable|in:user,student,teacher,volunteer,admin',
      'user_ids' => 'required_if:audience,users|nullable|array|max:1000',
      'user_ids.*' => 'integer',
    ];
  }

  private function audienceValue(array $data)
  {
    return match ($data['audience']) {
      'role' => $data['role'],
      'users' => array_values(array_unique(array_map('intval', $data['user_ids'] ?? []))),
      default => null,
    };
  }

  /**
   * How many users / devices a broadcast would reach.
   */
  public function broadcastAudience(Request $request)
  {
    $data = $request->validate($this->audienceRules());
    $ids = AdminBroadcast::recipientsQuery($data['audience'], $this->audienceValue($data));

    return response()->json([
      'users' => (clone $ids)->count(),
      'web_devices' => NotificationSubscription::whereIn('user_id', clone $ids)
        ->where(fn($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
        ->count(),
      'mobile_devices' => ExpoPushToken::whereIn('user_id', clone $ids)->where('is_active', true)->count(),
    ]);
  }

  public function broadcasts(Request $request)
  {
    return response()->json(
      AdminBroadcast::with('admin:id,username')->orderByDesc('id')->paginate($this->perPage($request))
    );
  }

  public function sendBroadcast(Request $request)
  {
    $data = $request->validate($this->audienceRules() + [
      'title' => 'required|string|max:120',
      'body' => 'required|string|max:500',
      'url' => ['nullable', 'string', 'max:255', 'regex:#^(/|https://)#'],
      'topic_id' => 'nullable|integer|exists:cyo_topics,id',
      'channels' => 'required|array|min:1',
      'channels.*' => 'in:web,mobile',
      'save_to_inbox' => 'boolean',
    ]);

    $broadcast = AdminBroadcast::create([
      'admin_id' => Auth::id(),
      'title' => $data['title'],
      'body' => $data['body'],
      'url' => $data['url'] ?? null,
      'topic_id' => $data['topic_id'] ?? null,
      'audience' => $data['audience'],
      'audience_value' => $this->audienceValue($data),
      'channels' => array_values(array_unique($data['channels'])),
      'save_to_inbox' => $data['save_to_inbox'] ?? true,
      'status' => 'queued',
    ]);

    // Runs inline when QUEUE_CONNECTION=sync, otherwise on the queue worker.
    SendAdminBroadcast::dispatch($broadcast->id);

    return response()->json([
      'message' => 'Đã gửi thông báo.',
      'broadcast' => $broadcast->fresh(),
    ], 201);
  }
}
