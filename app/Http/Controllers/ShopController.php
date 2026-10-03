<?php

namespace App\Http\Controllers;

use App\Models\AuthAccount;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\ShopCartItem;
use App\Models\ShopCategory;
use App\Models\ShopOrder;
use App\Models\ShopProduct;
use App\Models\ShopProductVariant;
use App\Services\PointsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ShopController extends Controller
{
  // Same SePay QR image endpoint + bank account the wallet deposit flow
  // uses (WalletController::createDepositRequest, mobile's DepositScreen) -
  // reusing it here keeps "pay by QR" behaviorally identical to the wallet
  // top-up flow the user already knows, instead of a second QR pattern.
  const BANK_ACCOUNT = '99421112003';
  const BANK_NAME = 'TPBank';
  const BANK_ACCOUNT_HOLDER = 'DUONG TUNG ANH';

  public function index(Request $request)
  {
    $query = ShopProduct::where('is_active', true)->with('category')->withCount('variants');

    if ($request->has('category_id')) {
      $query->where('category_id', $request->category_id);
    }

    if ($request->has('search')) {
      $query->where('name', 'like', '%' . $request->search . '%');
    }

    return response()->json($query->paginate(12));
  }

  public function show($id)
  {
    $product = ShopProduct::with(['category', 'variants'])->findOrFail($id);
    return response()->json($product);
  }

  public function categories()
  {
    $categories = ShopCategory::withCount('products')->get();
    return response()->json($categories);
  }

  public function storeOrder(Request $request)
  {
    $request->validate([
      'items' => 'required|array|min:1',
      'items.*.product_id' => 'required|exists:cyo_shop_products,id',
      'items.*.variant_id' => 'nullable|integer',
      'items.*.quantity' => 'required|integer|min:1',
      'shipping_address' => 'required|string',
      'phone' => 'required|string',
      'note' => 'nullable|string',
      'payment_method' => 'required|in:points,qr,cod',
    ]);

    $user = $request->user();
    // Read the rate off the same constant the /student-verification/status
    // endpoint reports to clients - hardcoding 0.10 here as well meant
    // changing the advertised discount would silently keep charging the old
    // one at checkout.
    $studentDiscount = $user->student_verified_at
      ? StudentVerificationController::DISCOUNT_PERCENT / 100
      : 0;

    $order = DB::transaction(function () use ($request, $user, $studentDiscount) {
      $totalAmount = 0;
      $items = [];

      foreach ($request->items as $itemData) {
        $product = ShopProduct::lockForUpdate()->findOrFail($itemData['product_id']);
        $variant = null;

        if ($product->variants()->exists()) {
          $variant = ShopProductVariant::lockForUpdate()
            ->where('product_id', $product->id)
            ->find($itemData['variant_id'] ?? null);
          if (!$variant) {
            throw new \Exception("Vui lòng chọn phân loại cho sản phẩm {$product->name}.");
          }
        }

        $stockHolder = $variant ?? $product;
        if ($stockHolder->stock < $itemData['quantity']) {
          throw new \Exception("Sản phẩm {$product->name}" . ($variant ? " ({$variant->label()})" : '') . " không đủ hàng.");
        }

        $stockHolder->decrement('stock', $itemData['quantity']);
        if ($variant) {
          // Product stock mirrors the sum of its variants.
          $product->decrement('stock', $itemData['quantity']);
        }
        $price = $stockHolder->price;
        $discountedPrice = round($price * (1 - $studentDiscount));
        $totalAmount += $discountedPrice * $itemData['quantity'];

        $items[] = [
          'product_id' => $product->id,
          'variant_id' => $variant?->id,
          'variant_label' => $variant?->label(),
          'quantity' => $itemData['quantity'],
          'price' => $discountedPrice,
        ];
      }

      $shippingFee = 15000;
      $totalAmount += $shippingFee;

      $order = ShopOrder::create([
        'user_id' => $user->id,
        'total_amount' => $totalAmount,
        'discount_percent' => $studentDiscount > 0 ? intval($studentDiscount * 100) : null,
        // COD is accepted straight into fulfillment since nothing is owed
        // up front; points/qr stay "pending" until payment actually lands
        // (points: a few lines below in this same transaction; qr: the
        // SePay webhook, see SEPayWebhookController::processDeposit).
        'status' => $request->payment_method === 'cod' ? 'processing' : 'pending',
        'shipping_address' => $request->shipping_address,
        'phone' => $request->phone,
        'note' => $request->note,
        'payment_method' => $request->payment_method,
        'payment_status' => 'pending',
      ]);

      foreach ($items as $item) {
        $order->items()->create($item);
      }

      if ($request->payment_method === 'points') {
        $pointsNeeded = PointsService::convertVNDToPoints($totalAmount);
        if (($user->points ?? 0) < $pointsNeeded) {
          throw new \Exception('Số dư điểm không đủ để thanh toán đơn hàng này.');
        }

        $deducted = PointsService::deductPoints(
          $user->id,
          $pointsNeeded,
          'purchase',
          "Thanh toán đơn hàng Giftshop #{$order->id}",
          $order->id,
        );
        if (!$deducted) {
          throw new \Exception('Không thể trừ điểm. Vui lòng thử lại.');
        }

        $order->update([
          'payment_status' => 'paid',
          'status' => 'processing',
          'paid_at' => now(),
        ]);
      } elseif ($request->payment_method === 'qr') {
        // Mirrors WalletController::createDepositRequest's MW<user_id><timestamp>
        // pattern - "GS" (Gift Shop) instead of "MW" is what lets the shared
        // SePay webhook tell an order payment apart from a wallet deposit.
        $paymentCode = 'GS' . $order->id . time();
        $order->update(['payment_code' => $paymentCode]);
      }

      return $order;
    });

    $response = [
      'message' => 'Đơn hàng đã được tạo thành công.',
      'order' => $order->load('items.product'),
    ];

    if ($order->payment_method === 'qr') {
      $response['payment'] = $this->buildQrPayment($order);
    }

    return response()->json($response, 201);
  }

  /**
   * QR + bank transfer details for an order awaiting payment - same shape
   * the frontend/mobile already render for wallet deposits.
   */
  private function buildQrPayment(ShopOrder $order): array
  {
    return [
      'payment_code' => $order->payment_code,
      'amount_vnd' => $order->total_amount,
      'bank_name' => self::BANK_NAME,
      'bank_account' => self::BANK_ACCOUNT,
      'bank_account_holder' => self::BANK_ACCOUNT_HOLDER,
      'qr_url' => "https://qr.sepay.vn/img?acc=" . self::BANK_ACCOUNT
        . "&bank=" . self::BANK_NAME
        . "&amount=" . $order->total_amount
        . "&des=" . $order->payment_code
        . "&template=compact",
      'instructions' => "Chuyển khoản {$order->total_amount} VND với nội dung: {$order->payment_code}",
    ];
  }

  /**
   * Lets the client poll for payment confirmation the same way the mobile
   * wallet deposit screen polls getWalletBalance() - simpler than wiring up
   * websockets/push for what's already a short-lived wait.
   */
  public function paymentStatus(Request $request, $id)
  {
    $order = ShopOrder::where('user_id', $request->user()->id)->findOrFail($id);

    $data = [
      'order_id' => $order->id,
      'status' => $order->status,
      'payment_status' => $order->payment_status,
      'paid_at' => $order->paid_at,
    ];

    if ($order->payment_method === 'qr' && $order->payment_status === 'pending') {
      $data['payment'] = $this->buildQrPayment($order);
    }

    return response()->json($data);
  }

  /**
   * Lets a user back out of an order that hasn't been paid yet (qr still
   * pending, or points/cod not yet processed past pending) - restores the
   * stock that storeOrder reserved.
   */
  public function cancelOrder(Request $request, $id)
  {
    $order = ShopOrder::where('user_id', $request->user()->id)
      ->with('items')
      ->findOrFail($id);

    if ($order->payment_status === 'paid' && $order->payment_method !== 'cod') {
      return response()->json([
        'message' => 'Đơn hàng đã thanh toán, không thể hủy.',
      ], 422);
    }

    if (in_array($order->status, ['shipped', 'completed', 'cancelled'])) {
      return response()->json([
        'message' => 'Đơn hàng ở trạng thái này không thể hủy.',
      ], 422);
    }

    DB::transaction(function () use ($order) {
      foreach ($order->items as $item) {
        $item->restock();
      }
      $order->update(['status' => 'cancelled', 'payment_status' => 'failed']);
    });

    return response()->json(['message' => 'Đơn hàng đã được hủy.', 'order' => $order]);
  }

  public function myOrders(Request $request)
  {
    $orders = ShopOrder::where('user_id', $request->user()->id)
      ->with('items.product')
      ->orderBy('created_at', 'desc')
      ->paginate(10);
    return response()->json($orders);
  }

  /**
   * "Nhắn tin" on a product page: posts an inquiry into the customer's one
   * ongoing shop-support thread, a group conversation shared with every
   * shop admin (not a 1-on-1 with a single "assigned" admin) so any of them
   * can pick it up and the customer sees who actually replied. Reuses the
   * existing chat system (Conversation/Message + ChatController's broadcast
   * pipeline) rather than a separate inbox, so replies show up in the same
   * admin chat inbox they already use.
   */
  public function contactShop(Request $request, $id)
  {
    $product = ShopProduct::findOrFail($id);
    $user = $request->user();
    $variant = $request->filled('variant_id')
      ? ShopProductVariant::where('product_id', $product->id)->find($request->input('variant_id'))
      : null;

    $conversation = Conversation::where('is_shop_support', true)
      ->whereHas('participants', fn($q) => $q->where('user_id', $user->id))
      ->first();

    $adminIds = AuthAccount::where('role', 'admin')->pluck('id');

    if (!$conversation) {
      $conversation = Conversation::create([
        'type' => 'group',
        'name' => 'Hỗ trợ - ' . ($user->profile->profile_name ?? $user->username),
        'created_by' => $user->id,
        'is_shop_support' => true,
      ]);
      $conversation->participants()->attach($user->id, ['role' => 'owner']);
      $conversation->participants()->attach($adminIds->all(), ['role' => 'member']);
    } else {
      // Self-heals membership for admins granted the role after the thread
      // was created, so they see it next time a customer writes in without
      // needing to already be a participant (attach() is a no-op for ids
      // already in the pivot table).
      $existingIds = $conversation->participants()->pluck('cyo_auth_accounts.id');
      $conversation->participants()->attach($adminIds->diff($existingIds)->all(), ['role' => 'member']);
    }

    $message = Message::create([
      'conversation_id' => $conversation->id,
      'user_id' => $user->id,
      'content' => sprintf(
        "Mình quan tâm đến sản phẩm \"%s\" (%s đ), shop tư vấn giúp mình nhé.",
        $product->name,
        number_format($product->price, 0, ',', '.'),
      ),
      'type' => 'text',
      'metadata' => array_filter([
        'shop_product_id' => $product->id,
        'shop_product_name' => $product->name,
        // The variant picked on the product page, if any - context for the
        // staff and for the AI assistant.
        'shop_variant_id' => $variant?->id,
        'shop_variant_label' => $variant?->label(),
      ]),
    ]);

    app(ChatController::class)->broadcastAiMessage($conversation, $message, $user);

    // The inquiry is posted here rather than through ChatController, so it
    // has to ask for its AI answer itself.
    if ($conversation->shop_ai_enabled ?? true) {
      \App\Jobs\GenerateAiChatReply::dispatch($conversation->id, $message->id, 'shop');
    }

    return response()->json([
      'conversation_id' => $conversation->id,
      'admins_online' => AuthAccount::role('admin')->online()->count(),
      'ai_enabled' => (bool) ($conversation->shop_ai_enabled ?? true),
    ], $conversation->wasRecentlyCreated ? 201 : 200);
  }

  /**
   * The customer's AI switch for their support thread: on = the AI answers
   * their messages, off = wait for a real person. Staff see a system message
   * in the thread whenever it changes.
   */
  public function setSupportAi(Request $request, $conversationId)
  {
    $data = $request->validate(['enabled' => 'required|boolean']);

    $conversation = Conversation::where('is_shop_support', true)
      ->where('created_by', $request->user()->id)
      ->findOrFail($conversationId);

    $enabled = (bool) $data['enabled'];

    if ($conversation->shop_ai_enabled !== $enabled) {
      $conversation->shop_ai_enabled = $enabled;
      $conversation->save();

      app(ChatController::class)->createSystemMessage(
        $conversation,
        $enabled
          ? 'Khách đã bật lại trả lời tự động bằng AI.'
          : 'Khách đã tắt AI và muốn trò chuyện với nhân viên shop.',
        ['shop_ai_enabled' => $enabled]
      );
    }

    return response()->json([
      'ai_enabled' => $enabled,
      'message' => $enabled
        ? 'Đã bật trả lời tự động bằng AI.'
        : 'Đã tắt AI. Nhân viên shop sẽ trả lời bạn.',
    ]);
  }

  /**
   * The account's cart, so it follows the user across devices. Lines whose
   * product or variant no longer exists are dropped.
   */
  public function cart(Request $request)
  {
    return response()->json($this->cartPayload($request->user()->id));
  }

  /**
   * Replace the account's cart with the given lines (the client sends its
   * whole cart after each change).
   */
  public function replaceCart(Request $request)
  {
    $data = $request->validate([
      'items' => 'present|array|max:100',
      'items.*.product_id' => 'required|integer',
      'items.*.variant_id' => 'nullable|integer',
      'items.*.quantity' => 'required|integer|min:1|max:999',
    ]);

    $userId = $request->user()->id;
    $productIds = ShopProduct::whereIn('id', collect($data['items'])->pluck('product_id'))->pluck('id')->flip();

    // Same product + variant twice in the payload = one line.
    $lines = collect($data['items'])
      ->filter(fn($item) => $productIds->has($item['product_id']))
      ->keyBy(fn($item) => $item['product_id'] . ':' . (int) ($item['variant_id'] ?? 0));

    DB::transaction(function () use ($userId, $lines) {
      ShopCartItem::where('user_id', $userId)->delete();

      foreach ($lines as $item) {
        ShopCartItem::create([
          'user_id' => $userId,
          'product_id' => $item['product_id'],
          'variant_id' => (int) ($item['variant_id'] ?? 0),
          'quantity' => $item['quantity'],
        ]);
      }
    });

    return response()->json($this->cartPayload($userId));
  }

  private function cartPayload(int $userId): array
  {
    $rows = ShopCartItem::where('user_id', $userId)->orderBy('id')->get();
    $products = ShopProduct::with(['category', 'variants'])
      ->whereIn('id', $rows->pluck('product_id'))
      ->get()
      ->keyBy('id');

    $items = [];
    foreach ($rows as $row) {
      $product = $products->get($row->product_id);
      $variant = $row->variant_id ? $product?->variants->firstWhere('id', $row->variant_id) : null;

      if (!$product || ($row->variant_id && !$variant)) {
        continue;
      }

      $items[] = ['product' => $product, 'variant' => $variant, 'quantity' => $row->quantity];
    }

    return [
      'items' => $items,
      // Lets a client tell whether another device changed the cart.
      'updated_at' => optional($rows->max('updated_at'))->toISOString(),
    ];
  }

  /**
   * Lets the chat widget show "Đang online" / "Ngoại tuyến" for shop admins
   * without re-posting a message - polled while the widget is open so the
   * indicator stays live if an admin comes online/offline mid-conversation.
   */
  public function supportStatus(Request $request)
  {
    // The customer's own thread, for its AI switch (null before first contact).
    $conversation = Conversation::where('is_shop_support', true)
      ->where('created_by', $request->user()->id)
      ->first();

    return response()->json([
      'admins_online' => AuthAccount::role('admin')->online()->count(),
      'ai_enabled' => $conversation ? (bool) $conversation->shop_ai_enabled : true,
    ]);
  }
}
