<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\ShopOrder;

class ShopOrderCompletedMail extends Mailable implements ShouldQueue
{
  use Queueable, SerializesModels;

  public ShopOrder $order;

  public function __construct(ShopOrder $order)
  {
    $this->order = $order;
  }

  public function envelope(): Envelope
  {
    return new Envelope(
      subject: 'Đơn hàng #' . $this->order->id . ' đã được giao thành công!',
    );
  }

  public function content(): Content
  {
    $items = $this->order->items->map(fn($item) => [
      'name' => $item->product?->name ?? 'Sản phẩm #' . $item->product_id,
      'variant' => $item->variant_label,
      'quantity' => $item->quantity,
      'subtotal' => number_format($item->price * $item->quantity, 0, ',', '.'),
    ])->all();

    return new Content(
      markdown: 'emails.shop_order_completed',
      with: [
        'orderId'    => $this->order->id,
        'items'      => $items,
        'total'      => number_format($this->order->total_amount, 0, ',', '.'),
        'shopUrl'    => env('SHOP_UI_URL', 'https://shop.chuyenbienhoa.com'),
        'ordersUrl'  => env('SHOP_UI_URL', 'https://shop.chuyenbienhoa.com') . '/orders',
      ],
    );
  }

  public function attachments(): array
  {
    return [];
  }
}
