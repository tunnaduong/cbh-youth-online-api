<?php

namespace App\Http\Controllers;

use App\Models\ShopCategory;
use App\Models\ShopOrder;
use App\Models\ShopProduct;
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
  const BANK_ACCOUNT_HOLDER = 'CHUYEN BIEN HOA';

  public function index(Request $request)
  {
    $query = ShopProduct::where('is_active', true)->with('category');

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
    $product = ShopProduct::with('category')->findOrFail($id);
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
      'items.*.quantity' => 'required|integer|min:1',
      'shipping_address' => 'required|string',
      'phone' => 'required|string',
      'note' => 'nullable|string',
      'payment_method' => 'required|in:points,qr,cod',
    ]);

    $user = $request->user();

    $order = DB::transaction(function () use ($request, $user) {
      $totalAmount = 0;
      $items = [];

      foreach ($request->items as $itemData) {
        $product = ShopProduct::lockForUpdate()->findOrFail($itemData['product_id']);

        if ($product->stock < $itemData['quantity']) {
          throw new \Exception("Sản phẩm {$product->name} không đủ hàng.");
        }

        $product->decrement('stock', $itemData['quantity']);
        $itemTotal = $product->price * $itemData['quantity'];
        $totalAmount += $itemTotal;

        $items[] = [
          'product_id' => $product->id,
          'quantity' => $itemData['quantity'],
          'price' => $product->price,
        ];
      }

      $order = ShopOrder::create([
        'user_id' => $user->id,
        'total_amount' => $totalAmount,
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
        ShopProduct::where('id', $item->product_id)->increment('stock', $item->quantity);
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
}
