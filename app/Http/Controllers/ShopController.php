<?php

namespace App\Http\Controllers;

use App\Models\ShopCategory;
use App\Models\ShopOrder;
use App\Models\ShopProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ShopController extends Controller
{
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
    ]);

    return DB::transaction(function () use ($request) {
      $user = $request->user();
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
        'status' => 'pending',
        'shipping_address' => $request->shipping_address,
        'phone' => $request->phone,
        'note' => $request->note,
      ]);

      foreach ($items as $item) {
        $order->items()->create($item);
      }

      return response()->json([
        'message' => 'Đơn hàng đã được tạo thành công.',
        'order' => $order->load('items.product'),
      ], 201);
    });
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
