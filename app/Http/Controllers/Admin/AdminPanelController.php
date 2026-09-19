<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendAdminBroadcast;
use App\Models\AdminBroadcast;
use App\Models\AdminMessageAccessLog;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\AuthAccount;
use App\Models\ExpoPushToken;
use App\Models\NotificationSubscription;
use App\Models\PendingDeposit;
use App\Models\ShopCategory;
use App\Models\ShopOrder;
use App\Models\ShopProduct;
use App\Models\ShopProductVariant;
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
    $query = ShopProduct::query()->with(['category:id,name', 'variants']);
    $this->applySearch($query, $request, ['name', 'slug', 'sku'], false);

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
      'sku' => 'nullable|string|max:64|unique:cyo_shop_products,sku' . ($id ? ",{$id}" : ''),
      'description' => 'nullable|string',
      'price' => 'required|integer|min:0',
      'stock' => 'required|integer|min:0',
      'image_url' => 'nullable|string|max:255',
      'category_id' => 'required|exists:cyo_shop_categories,id',
      'is_active' => 'boolean',
      'options' => 'nullable|array|max:3',
      'options.*.name' => 'required|string|max:50|distinct',
      'options.*.values' => 'required|array|min:1|max:30',
      'options.*.values.*' => 'required|string|max:50|distinct',
      'variants' => 'nullable|array|max:200',
      'variants.*.id' => 'nullable|integer',
      'variants.*.options' => 'required|array',
      'variants.*.sku' => 'nullable|string|max:64|distinct',
      'variants.*.price' => 'required|integer|min:0',
      'variants.*.stock' => 'required|integer|min:0',
      'variants.*.image_url' => 'nullable|string|max:255',
    ]);
    $data['slug'] = $data['slug'] ?: Str::slug($data['name']) . '-' . Str::lower(Str::random(4));

    $options = array_values($data['options'] ?? []);
    $variants = $options ? ($data['variants'] ?? []) : [];
    unset($data['variants']);
    $data['options'] = $options ?: null;

    if ($options && !$variants) {
      return response()->json(['message' => 'Sản phẩm có phân loại cần ít nhất một biến thể.'], 422);
    }
    if ($variants) {
      // Listing shows the cheapest variant; stock is the sum of all variants.
      $data['price'] = min(array_column($variants, 'price'));
      $data['stock'] = array_sum(array_column($variants, 'stock'));
    }

    $product = $id ? ShopProduct::findOrFail($id) : new ShopProduct();

    try {
      DB::transaction(function () use ($product, $data, $variants) {
        $product->fill($data)->save();

        $keep = [];
        foreach ($variants as $v) {
          $variant = !empty($v['id'])
            ? $product->variants()->find($v['id']) ?? new ShopProductVariant()
            : new ShopProductVariant();
          $variant->fill([
            'product_id' => $product->id,
            'options' => $v['options'],
            'sku' => $v['sku'] ?: null,
            'price' => $v['price'],
            'stock' => $v['stock'],
            'image_url' => $v['image_url'] ?? null,
          ])->save();
          $keep[] = $variant->id;
        }
        $product->variants()->whereNotIn('id', $keep)->delete();
      });
    } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
      return response()->json(['message' => 'Mã biến thể (SKU) bị trùng.'], 422);
    }

    return response()->json(['message' => 'Đã lưu sản phẩm.', 'product' => $product->load('variants')]);
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
          $item->restock();
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

  // ---------------------------------------------------------------- Messages (audited)

  private function logMessageAccess(Request $request, string $action, ?int $conversationId = null, ?string $query = null): void
  {
    AdminMessageAccessLog::create([
      'admin_id' => Auth::id(),
      'conversation_id' => $conversationId,
      'action' => $action,
      'query' => $query ? Str::limit($query, 250, '') : null,
      'ip' => $request->ip(),
    ]);
  }

  /**
   * Conversations list. Does not expose message content, so it is not logged.
   */
  public function conversations(Request $request)
  {
    $query = Conversation::query()
      ->select(['id', 'type', 'name', 'is_public', 'created_by', 'created_at', 'updated_at'])
      ->with('participants:id,username')
      ->withCount('messages')
      ->withMax('messages', 'created_at');

    $search = trim((string) $request->input('search', ''));
    if ($search !== '') {
      $query->where(function ($q) use ($search) {
        $q->where('name', 'like', "%{$search}%")
          ->orWhereHas('participants', fn($p) => $p->where('username', 'like', "%{$search}%"));
        if (ctype_digit($search)) {
          $q->orWhere('cyo_conversations.id', (int) $search);
        }
      });
    }
    if ($request->filled('user_id')) {
      $query->whereHas('participants', fn($p) => $p->where('cyo_auth_accounts.id', $request->user_id));
    }
    if ($request->filled('type')) {
      $query->where('type', $request->type);
    }

    return response()->json(
      $query->orderByDesc('messages_max_created_at')->paginate($this->perPage($request))
    );
  }

  /**
   * Messages of one conversation, newest page first (use before_id to page back).
   * Includes deleted/recalled messages, flagged, so moderators see the full record.
   */
  public function conversationMessages(Request $request, $id)
  {
    $conversation = Conversation::with('participants:id,username')->findOrFail($id);

    // Log once per conversation open, not on every "load older" page.
    if (!$request->filled('before_id')) {
      $this->logMessageAccess($request, 'view_conversation', $conversation->id);
    }

    $messages = Message::withTrashed()
      ->where('conversation_id', $conversation->id)
      ->when($request->filled('before_id'), fn($q) => $q->where('id', '<', (int) $request->before_id))
      ->with('user:id,username')
      ->orderByDesc('id')
      ->limit(50)
      ->get(['id', 'conversation_id', 'user_id', 'guest_name', 'content', 'type', 'file_url', 'file_urls',
        'is_edited', 'is_recalled', 'reply_to_message_id', 'is_forwarded', 'created_at', 'deleted_at']);

    return response()->json([
      'conversation' => $conversation,
      'messages' => $messages->reverse()->values(),
      'has_more' => $messages->count() === 50,
    ]);
  }

  /**
   * Full-text-ish search across all messages (logged).
   */
  public function searchMessages(Request $request)
  {
    $data = $request->validate([
      'search' => 'nullable|string|min:2|max:250',
      'user_id' => 'nullable|integer',
    ]);
    if (empty($data['search']) && empty($data['user_id'])) {
      return response()->json(['message' => 'Nhập từ khóa hoặc chọn người dùng.'], 422);
    }

    $this->logMessageAccess($request, 'search_messages', null,
      trim(($data['search'] ?? '') . (isset($data['user_id']) ? " user:{$data['user_id']}" : '')));

    $query = Message::withTrashed()
      ->with(['user:id,username', 'conversation:id,type,name'])
      ->when($data['search'] ?? null, fn($q, $s) => $q->where('content', 'like', "%{$s}%"))
      ->when($data['user_id'] ?? null, fn($q, $u) => $q->where('user_id', $u));

    return response()->json($query->orderByDesc('id')->paginate($this->perPage($request)));
  }

  public function messageAccessLogs(Request $request)
  {
    return response()->json(
      AdminMessageAccessLog::with(['admin:id,username', 'conversation:id,type,name'])
        ->orderByDesc('id')
        ->paginate($this->perPage($request))
    );
  }
}
