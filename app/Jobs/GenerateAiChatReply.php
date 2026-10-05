<?php

namespace App\Jobs;

use App\Events\AiTyping;
use App\Http\Controllers\ChatController;
use App\Models\AuthAccount;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\AiChatService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Runs the Groq call for a /ai, /summary, or reply-to-AI trigger out of
 * band, then creates and broadcasts Yoyo AI's reply message the same way a
 * human-sent message would be (see ChatController::finalizeAndBroadcastMessage).
 */
class GenerateAiChatReply implements ShouldQueue
{
  use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

  public int $timeout = 90;
  public int $tries = 1;

  /**
   * @param  int  $conversationId
   * @param  int  $triggerMessageId  The user message that triggered this (used as reply_to for the AI's answer).
   * @param  string  $mode  'ai' | 'summary'
   */
  public function __construct(
    private int $conversationId,
    private int $triggerMessageId,
    private string $mode,
  ) {}

  public function handle(AiChatService $aiChatService): void
  {
    $conversation = Conversation::find($this->conversationId);
    $triggerMessage = Message::find($this->triggerMessageId);
    $aiAccount = AuthAccount::where('is_ai', true)->first();

    if (!$conversation || !$triggerMessage || !$aiAccount) {
      Log::warning('GenerateAiChatReply: missing conversation/message/AI account, skipping.');
      return;
    }

    // The customer may have switched the AI off while this job was queued.
    if ($this->mode === 'shop' && !$conversation->shop_ai_enabled) {
      return;
    }

    // Fire the AI's "typing" indicator now, since the Groq call below can
    // take several seconds - the AI has no client to whisper the way humans
    // do (see ChatProvider.js/ChatSocketContext.js), so this is a real
    // broadcast instead. Always stopped in `finally`, including on error,
    // so a failed job never leaves a stuck indicator.
    broadcast(new AiTyping($conversation->id, $aiAccount->id, $aiAccount->avatarUrl(), true));

    try {
      try {
        $result = match ($this->mode) {
          'summary' => $this->runSummary($aiChatService, $conversation, $triggerMessage),
          'shop' => $this->runShopSupport($aiChatService, $conversation, $triggerMessage),
          default => $this->runAsk($aiChatService, $triggerMessage),
        };
      } catch (\Throwable $e) {
        Log::error('GenerateAiChatReply failed: ' . $e->getMessage());
        $result = ['content' => 'Xin lỗi, hiện tại AI đang gặp sự cố và không thể trả lời. Vui lòng thử lại sau.', 'reaction' => null];
      }

      $aiMessage = Message::create([
        'conversation_id' => $conversation->id,
        'user_id' => $aiAccount->id,
        'content' => $result['content'],
        'type' => 'text',
        // Shop support only: product photos, an order slip or a QR to pay,
        // for the shop's chat widget to draw under the text.
        'metadata' => $result['metadata'] ?? null,
        // A support thread reads as a normal back-and-forth, and a quoted
        // reply would also send the customer a "replied to you" notification
        // for every answer.
        'reply_to_message_id' => $this->mode === 'shop' ? null : $triggerMessage->id,
      ]);

      $chatController = app(ChatController::class);
      $chatController->broadcastAiMessage($conversation, $aiMessage, $aiAccount);

      // Optional: the AI can also react to the original message it was called
      // on (its own choice, expressed via the "[REACT:type]" marker parsed out
      // in AiChatService) - never on its own canned fallback/error/help replies,
      // only when it actually got a real model response.
      if ($result['reaction']) {
        $chatController->reactAsAi($triggerMessage, $aiAccount, $result['reaction']);
      }
    } finally {
      broadcast(new AiTyping($conversation->id, $aiAccount->id, $aiAccount->avatarUrl(), false));
    }
  }

  /**
   * Gift shop support: answer the customer with the thread's recent history
   * plus what the shop knows about them and the product they asked about.
   *
   * @return array{content: string, reaction: ?string}
   */
  private function runShopSupport(AiChatService $aiChatService, Conversation $conversation, Message $triggerMessage): array
  {
    $history = $this->collectRecentHistory($conversation, $triggerMessage, 20)
      ->map(fn($m) => $this->toContext($m))
      ->all();

    $result = $aiChatService->askShopSupport(
      $history,
      (string) $triggerMessage->content,
      $this->buildShopContext($conversation)
    );

    // What the assistant asked to attach, checked against the shop's own
    // data: the model only ever names ids, the photos, prices and payment
    // details all come from the database.
    $customer = AuthAccount::find($conversation->created_by);
    $content = trim($result['content']);
    $metadata = [];

    $images = $this->resolveProductImages($result['images'] ?? []);
    if ($images) {
      $metadata['shop_images'] = $images;
    }

    if (is_array($result['order'] ?? null) && $customer) {
      [$draft, $problem] = $this->buildOrderDraft($result['order'], $customer);
      if ($draft) {
        $metadata['shop_order_draft'] = $draft;
      } else {
        // The text most likely promises a slip that isn't there.
        $content = trim($content . "\n\n(Mình chưa lập được phiếu đặt hàng: {$problem} Bạn kiểm tra lại giúp mình nhé.)");
      }
    }

    if (!empty($result['pay_order_id']) && $customer) {
      $payment = $this->resolvePayment((int) $result['pay_order_id'], $customer);
      if ($payment) {
        $metadata['shop_payment'] = $payment;
      }
    }

    if ($content === '') {
      // The model answered with markers only.
      $content = match (true) {
        isset($metadata['shop_order_draft']) => 'Mình đã lập phiếu đặt hàng bên dưới, bạn kiểm tra rồi bấm "Xác nhận đặt hàng" nhé.',
        isset($metadata['shop_payment']) => 'Mình gửi lại mã QR thanh toán cho bạn nhé.',
        isset($metadata['shop_images']) => 'Mình gửi bạn ảnh sản phẩm nhé.',
        default => 'Xin lỗi, mình chưa hiểu ý bạn. Bạn nói rõ hơn giúp mình nhé.',
      };
    }

    return [
      'content' => $content,
      // No emoji reactions from the support assistant.
      'reaction' => null,
      'metadata' => $metadata ?: null,
    ];
  }

  /**
   * Turns the [IMAGE:..] ids into what the widget shows: the product (or
   * variant) photo stored by the shop, with its name and price. Products
   * that are off sale or have no photo are skipped.
   *
   * @param  array<int, array{product_id: int, variant_id: ?int}>  $requests
   */
  private function resolveProductImages(array $requests): array
  {
    $images = [];

    foreach ($requests as $request) {
      $product = \App\Models\ShopProduct::where('is_active', true)->with('variants')->find($request['product_id']);
      if (!$product) {
        continue;
      }
      $variant = $request['variant_id'] ? $product->variants->firstWhere('id', $request['variant_id']) : null;
      $url = $variant?->image_url ?: $product->image_url;
      if (!$url || isset($images[$url])) {
        continue;
      }

      $images[$url] = [
        'product_id' => $product->id,
        'variant_id' => $variant?->id,
        'name' => $product->name,
        'variant_label' => $variant?->label(),
        'price' => (int) ($variant?->price ?? $product->price),
        'image_url' => $url,
      ];
    }

    return array_values($images);
  }

  /**
   * Checks the assistant's [ORDER] block and turns it into the slip the
   * customer confirms (ShopController::confirmChatOrder places the order
   * from exactly this). Everything the model wrote is re-read from the
   * database or validated here; a slip is only made when the order is
   * complete: known products and variants in stock, a recipient, a phone
   * number, a full address and a payment method.
   *
   * @return array{0: ?array, 1: ?string}  [slip, or null with the reason for the customer]
   */
  private function buildOrderDraft(array $raw, AuthAccount $customer): array
  {
    $name = trim((string) ($raw['recipient_name'] ?? ''));
    $phone = preg_replace('/[\s.\-()]/', '', (string) ($raw['phone'] ?? ''));
    $phone = preg_replace('/^\+84/', '0', $phone);
    $address = trim((string) ($raw['address'] ?? ''));
    $method = strtolower(trim((string) ($raw['payment_method'] ?? '')));
    $note = trim((string) ($raw['note'] ?? ''));

    if (mb_strlen($name) < 2) {
      return [null, 'thiếu họ tên người nhận.'];
    }
    if (!preg_match('/^0\d{8,10}$/', $phone)) {
      return [null, 'số điện thoại người nhận chưa hợp lệ.'];
    }
    if (mb_strlen($address) < 10) {
      return [null, 'địa chỉ giao hàng chưa đầy đủ.'];
    }
    if (!in_array($method, ['points', 'qr', 'cod'], true)) {
      return [null, 'chưa chọn phương thức thanh toán.'];
    }
    if (!is_array($raw['items'] ?? null) || !$raw['items']) {
      return [null, 'chưa có sản phẩm nào.'];
    }

    $discount = $customer->student_verified_at
      ? \App\Http\Controllers\StudentVerificationController::DISCOUNT_PERCENT / 100
      : 0;
    $items = [];
    $subtotal = 0;

    foreach (array_slice($raw['items'], 0, 20) as $line) {
      $product = \App\Models\ShopProduct::where('is_active', true)->with('variants')->find((int) ($line['product_id'] ?? 0));
      if (!$product) {
        return [null, 'có sản phẩm không còn bán.'];
      }

      $variant = null;
      if ($product->variants->isNotEmpty()) {
        $variant = $product->variants->firstWhere('id', (int) ($line['variant_id'] ?? 0));
        if (!$variant) {
          return [null, "chưa chọn phân loại cho \"{$product->name}\"."];
        }
        $variant->setRelation('product', $product);
      }

      $quantity = (int) ($line['quantity'] ?? 0);
      $stock = (int) ($variant?->stock ?? $product->stock);
      if ($quantity < 1) {
        return [null, "số lượng của \"{$product->name}\" chưa hợp lệ."];
      }
      if ($quantity > $stock) {
        return [null, "\"{$product->name}\" chỉ còn {$stock} sản phẩm."];
      }

      $price = (int) round(($variant?->price ?? $product->price) * (1 - $discount));
      $subtotal += $price * $quantity;
      $items[] = [
        'product_id' => $product->id,
        'variant_id' => $variant?->id,
        'quantity' => $quantity,
        'name' => $product->name,
        'variant_label' => $variant?->label(),
        'price' => $price,
        'image_url' => $variant?->image_url ?: $product->image_url,
      ];
    }

    // Same fee ShopController::placeOrder adds.
    $shippingFee = 15000;
    $total = $subtotal + $shippingFee;

    if ($method === 'points' && $customer->getPoints() < \App\Services\PointsService::convertVNDToPoints($total)) {
      return [null, 'số điểm hiện có không đủ để thanh toán đơn này.'];
    }

    return [[
      'items' => $items,
      'recipient_name' => $name,
      'phone' => $phone,
      'address' => $address,
      // An order has no recipient column: the name travels with the address.
      'shipping_address' => "{$name} - {$address}",
      'payment_method' => $method,
      'note' => $note !== '' ? mb_substr($note, 0, 500) : null,
      'discount_percent' => $discount > 0 ? (int) round($discount * 100) : null,
      'subtotal' => $subtotal,
      'shipping_fee' => $shippingFee,
      'total' => $total,
      // Set by confirmChatOrder once the customer has confirmed.
      'order_id' => null,
    ], null];
  }

  /**
   * The QR and transfer details for [PAY:order], only for the customer's own
   * order that is still waiting for a bank transfer.
   */
  private function resolvePayment(int $orderId, AuthAccount $customer): ?array
  {
    $order = \App\Models\ShopOrder::where('user_id', $customer->id)->find($orderId);
    if (
      !$order
      || $order->payment_method !== 'qr'
      || $order->payment_status !== 'pending'
      || $order->status === 'cancelled'
      || !$order->payment_code
    ) {
      return null;
    }

    return ['order_id' => $order->id] + app(\App\Http\Controllers\ShopController::class)->buildQrPayment($order);
  }

  /**
   * Everything the support assistant may rely on, as plain text: who the
   * customer is, the thread and its members, the product they last asked
   * about (with variants, prices and stock) and their recent orders.
   * Phone numbers and addresses are left out on purpose.
   */
  private function buildShopContext(Conversation $conversation): string
  {
    $money = fn($amount) => number_format((int) $amount, 0, ',', '.') . ' đ';
    $lines = [];

    $customer = AuthAccount::with('profile')->find($conversation->created_by);
    if ($customer) {
      $lines[] = 'Khách hàng: ' . ($customer->profile->profile_name ?? $customer->username)
        . " (@{$customer->username}), hiện có " . $customer->getPoints() . ' điểm.';
    }

    $staff = $conversation->participants()
      ->where('is_ai', false)
      ->where('cyo_auth_accounts.id', '!=', $conversation->created_by)
      ->with('profile')
      ->get()
      ->map(fn($member) => ($member->profile->profile_name ?? $member->username) . " (@{$member->username})")
      ->implode(', ');
    $lines[] = 'Nhóm chat hỗ trợ: "' . ($conversation->name ?: 'Hỗ trợ Giftshop') . '". Nhân viên shop trong nhóm: '
      . ($staff !== '' ? $staff : 'chưa có') . '.';

    // The product is whatever the customer's latest "Nhắn tin" inquiry was
    // about (ShopController::contactShop stores it in the message metadata).
    $inquiry = $conversation->messages()
      ->whereNotNull('metadata->shop_product_id')
      ->orderByDesc('id')
      ->first();
    $product = $inquiry
      ? \App\Models\ShopProduct::withTrashed()->with(['category', 'variants'])->find($inquiry->metadata['shop_product_id'] ?? null)
      : null;

    if ($product) {
      $lines[] = 'Sản phẩm khách đang hỏi: "' . $product->name . '"'
        . ($product->category ? ' - loại: ' . $product->category->name : '')
        . ' - giá: ' . $money($product->price)
        . ' - tồn kho: ' . (int) $product->stock
        . (!$product->is_active || $product->trashed() ? ' - HIỆN ĐÃ NGỪNG BÁN' : '') . '.';

      if (!empty($inquiry->metadata['shop_variant_label'])) {
        $lines[] = 'Phân loại khách đang chọn: ' . $inquiry->metadata['shop_variant_label'] . '.';
      }

      if ($product->description) {
        $lines[] = 'Mô tả sản phẩm: ' . \Illuminate\Support\Str::limit(trim(strip_tags($product->description)), 800);
      }

      if ($product->variants->isNotEmpty()) {
        $lines[] = 'Các phân loại: ' . $product->variants
          ->map(fn($variant) => $variant->label() . ' (giá ' . $money($variant->price) . ', còn ' . (int) $variant->stock . ')')
          ->implode('; ') . '.';
      }
    } else {
      $lines[] = 'Chưa xác định được sản phẩm cụ thể mà khách đang hỏi.';
    }

    // The catalogue, with the ids the assistant puts in its [ORDER] and
    // [IMAGE] markers: "#12" is a product, "[34]" one of its variants.
    $catalogue = \App\Models\ShopProduct::where('is_active', true)
      ->with(['category', 'variants'])
      ->orderBy('name')
      ->limit(60)
      ->get();

    if ($catalogue->isNotEmpty()) {
      $lines[] = 'Danh mục sản phẩm đang bán (mã sản phẩm sau dấu #, mã phân loại trong ngoặc vuông):';
      foreach ($catalogue as $item) {
        $line = "- #{$item->id} \"{$item->name}\""
          . ($item->category ? " ({$item->category->name})" : '')
          . ' - giá ' . $money($item->price)
          . ' - còn ' . (int) $item->stock
          . ($item->image_url ? ' - có ảnh' : ' - chưa có ảnh');

        if ($item->variants->isNotEmpty()) {
          $line .= ' - BẮT BUỘC chọn phân loại: ' . $item->variants
            ->map(function ($variant) use ($item, $money) {
              $variant->setRelation('product', $item);
              return "[{$variant->id}] " . $variant->label()
                . ' (giá ' . $money($variant->price) . ', còn ' . (int) $variant->stock
                . ($variant->image_url ? ', có ảnh riêng' : '') . ')';
            })
            ->implode('; ');
        }

        $lines[] = $line . '.';
      }
    } else {
      $lines[] = 'Hiện shop chưa có sản phẩm nào đang bán.';
    }

    if ($customer) {
      $lines[] = $customer->student_verified_at
        ? 'Khách là học sinh đã xác minh: được giảm ' . \App\Http\Controllers\StudentVerificationController::DISCOUNT_PERCENT . '% giá sản phẩm (không giảm phí vận chuyển).'
        : 'Khách chưa xác minh học sinh nên không có giảm giá học sinh.';
    }

    $orders = \App\Models\ShopOrder::where('user_id', $conversation->created_by)
      ->with('items.product')
      ->orderByDesc('created_at')
      ->limit(5)
      ->get();

    $statusLabels = [
      'pending' => 'chờ xử lý',
      'processing' => 'đang xử lý',
      'shipped' => 'đang giao',
      'completed' => 'hoàn tất',
      'cancelled' => 'đã hủy',
    ];
    $methodLabels = ['points' => 'điểm', 'qr' => 'chuyển khoản QR', 'cod' => 'COD (trả khi nhận hàng)'];

    if ($orders->isNotEmpty()) {
      $lines[] = 'Đơn hàng gần đây của khách (dữ liệu thanh toán là của hệ thống, cập nhật tại thời điểm này):';
      foreach ($orders as $order) {
        $items = $order->items
          ->map(fn($item) => ($item->product->name ?? 'Sản phẩm đã xóa')
            . ($item->variant_label ? " ({$item->variant_label})" : '')
            . ' x' . $item->quantity)
          ->implode(', ');

        // Spelled out, with the exact transfer details for an unpaid QR
        // order, so the assistant can answer "have I paid?" and "what do I
        // write in the transfer?" from facts.
        if ($order->status === 'cancelled') {
          $payment = 'đơn đã hủy';
        } elseif ($order->payment_status === 'paid') {
          $payment = 'ĐÃ THANH TOÁN' . ($order->paid_at ? ' lúc ' . \Illuminate\Support\Carbon::parse($order->paid_at)->format('H:i d/m/Y') : '');
        } elseif ($order->payment_method === 'cod') {
          $payment = 'thanh toán khi nhận hàng (chưa thu tiền)';
        } elseif ($order->payment_method === 'qr' && $order->payment_code) {
          $payment = 'CHƯA THANH TOÁN - cần chuyển khoản đúng ' . $money($order->total_amount)
            . ' tới ' . \App\Http\Controllers\ShopController::BANK_NAME
            . ', số tài khoản ' . \App\Http\Controllers\ShopController::BANK_ACCOUNT
            . ', chủ tài khoản ' . \App\Http\Controllers\ShopController::BANK_ACCOUNT_HOLDER
            . ', nội dung chuyển khoản phải ghi chính xác: ' . $order->payment_code;
        } else {
          $payment = 'CHƯA THANH TOÁN';
        }

        $lines[] = "- Đơn #{$order->id} đặt lúc " . $order->created_at->format('H:i d/m/Y')
          . ": {$items}; tổng " . $money($order->total_amount)
          . '; trạng thái: ' . ($statusLabels[$order->status] ?? $order->status)
          . '; phương thức: ' . ($methodLabels[$order->payment_method] ?? $order->payment_method)
          . "; thanh toán: {$payment}.";
      }
    } else {
      $lines[] = 'Khách chưa có đơn hàng nào.';
    }

    return implode("\n", $lines);
  }

  /**
   * @return array{content: string, reaction: ?string}
   */
  private function runAsk(AiChatService $aiChatService, Message $triggerMessage): array
  {
    $repliedTo = $triggerMessage->replyTo;
    if ($repliedTo && $repliedTo->is_recalled) {
      return ['content' => 'Xin lỗi, tin nhắn bạn trả lời đã bị thu hồi nên Yoyo AI không thể đọc được nội dung đó nữa.', 'reaction' => null];
    }
    if ($repliedTo && $repliedTo->type !== 'text') {
      return ['content' => $this->unsupportedMediaReply($repliedTo->type), 'reaction' => null];
    }

    $conversation = $triggerMessage->conversation;
    $chainMessages = $this->collectReplyChainMessages($triggerMessage);

    // A private 1-on-1 with Yoyo AI is a real back-and-forth conversation,
    // not a series of one-off commands - every earlier turn is relevant
    // context whether or not the user bothered to reply/quote a specific
    // message, so pull in recent history automatically here instead of
    // requiring an explicit /summary first. Elsewhere (group chats, the
    // public room) context still only ever comes from an explicit reply
    // chain, same as before.
    $contextMessages = $conversation->isPrivateAiConversation()
      ? $this->collectRecentHistory($conversation, $triggerMessage)
          ->merge($chainMessages)
          ->unique('id')
          ->sortBy('created_at')
          ->values()
      : $chainMessages;

    $chain = $contextMessages->map(fn($m) => $this->toContext($m))->all();
    $question = $this->stripCommandPrefix($triggerMessage->content ?? '', '/ai');

    return $aiChatService->askAi(
      $chain,
      $question !== '' ? $question : ($triggerMessage->content ?? ''),
      $this->buildConversationInfo($conversation)
    );
  }

  /**
   * The last $limit messages in the conversation (oldest first), excluding
   * the trigger message itself and recalled messages - same window
   * /summary already uses (see runSummary), reused here so a private AI
   * chat gets that same context automatically on every turn.
   *
   * @return \Illuminate\Support\Collection<int, Message>
   */
  private function collectRecentHistory(Conversation $conversation, Message $excludeMessage, int $limit = 30): \Illuminate\Support\Collection
  {
    return $conversation->messages()
      ->whereNotNull('content')
      ->where('is_recalled', false)
      ->where('id', '!=', $excludeMessage->id)
      ->with('user.profile')
      ->orderBy('created_at', 'desc')
      ->limit($limit)
      ->get()
      ->reverse()
      ->values();
  }

  /**
   * Basic info about the chat itself (name, type, member list) so /ai and
   * /summary can answer questions like "who's in this group" or "what's
   * this group called" without needing to be told explicitly.
   */
  private function buildConversationInfo(Conversation $conversation): string
  {
    if ($conversation->is_public) {
      return "Thông tin cuộc trò chuyện hiện tại: đây là phòng chat chung công khai \"{$conversation->name}\" của toàn bộ cộng đồng CYO/CBH Youth Online - ai cũng có thể tham gia, danh sách thành viên rất lớn và luôn thay đổi nên không liệt kê đầy đủ ở đây.";
    }

    if ($conversation->type === 'private') {
      $otherParticipant = $conversation->participants()
        ->where('is_ai', false)
        ->with('profile')
        ->first();
      $otherName = $otherParticipant
        ? ($otherParticipant->profile->profile_name ?? $otherParticipant->username)
        : 'người dùng';

      return "Thông tin cuộc trò chuyện hiện tại: đây là đoạn chat riêng (1-1) giữa bạn (Yoyo AI) và {$otherName}.";
    }

    // Group chat: name + member list (name, username, role).
    $members = $conversation->participants()
      ->where('is_ai', false)
      ->with('profile')
      ->get()
      ->map(function ($member) {
        $name = $member->profile->profile_name ?? $member->username;
        $role = $member->pivot->role ?? 'member';
        $roleLabel = match ($role) {
          'owner' => 'trưởng nhóm',
          'deputy' => 'phó nhóm',
          default => 'thành viên',
        };
        return "{$name} (@{$member->username}, {$roleLabel})";
      })
      ->implode(', ');

    $groupName = $conversation->name ?: 'Nhóm chưa đặt tên';

    return "Thông tin cuộc trò chuyện hiện tại: đây là nhóm chat tên \"{$groupName}\", gồm các thành viên: {$members}.";
  }

  /**
   * Yoyo AI can only read text today - if the message a user replied to (with
   * /ai, or by continuing a conversation with the AI) is a photo/video/file,
   * say so plainly instead of silently ignoring the attachment or hallucinating
   * about content it never actually saw.
   */
  private function unsupportedMediaReply(string $mediaType): string
  {
    $label = match ($mediaType) {
      'image' => 'hình ảnh',
      'video' => 'video',
      'file' => 'tệp đính kèm',
      default => 'nội dung này',
    };

    return "Xin lỗi, hiện tại Yoyo AI chưa thể đọc và xử lý {$label}, chỉ có thể đọc tin nhắn văn bản. Vui lòng thử lại với một tin nhắn chữ nhé.";
  }

  /**
   * @return array{content: string, reaction: ?string}
   */
  private function runSummary(AiChatService $aiChatService, Conversation $conversation, Message $triggerMessage): array
  {
    // /summary as a reply (e.g. replying to a specific message and asking to
    // summarize from there) only makes sense against text - same rule as /ai.
    $repliedTo = $triggerMessage->replyTo;
    if ($repliedTo && $repliedTo->is_recalled) {
      return ['content' => 'Xin lỗi, tin nhắn bạn trả lời đã bị thu hồi nên Yoyo AI không thể đọc được nội dung đó nữa.', 'reaction' => null];
    }
    if ($repliedTo && $repliedTo->type !== 'text') {
      return ['content' => $this->unsupportedMediaReply($repliedTo->type), 'reaction' => null];
    }

    $customRequest = $this->stripCommandPrefix($triggerMessage->content ?? '', '/summary');

    $recentQuery = $conversation->messages()
      ->whereNotNull('content')
      ->where('is_recalled', false)
      ->with('user.profile');

    // "/summary" as a reply anchors the window to end at the replied-to
    // message instead of "now" - so users can jump back and summarize an
    // older stretch of the conversation instead of always getting the very
    // latest messages.
    if ($repliedTo) {
      $recentQuery->where('created_at', '<=', $repliedTo->created_at);
    }

    $recent = $recentQuery
      ->orderBy('created_at', 'desc')
      ->limit(50)
      ->get()
      ->reverse()
      ->values();

    // Exclude the trigger message itself (the "/summary ..." command) from
    // the transcript being summarized - it's an instruction, not content.
    $recent = $recent->reject(fn($m) => $m->id === $triggerMessage->id)->values();

    $trimmed = $aiChatService->trimForSummary($recent);
    $context = $trimmed->map(fn($m) => $this->toContext($m))->all();

    return $aiChatService->summarizeAi(
      $context,
      $customRequest !== '' ? $customRequest : null,
      $this->buildConversationInfo($conversation)
    );
  }

  /**
   * Walk the reply_to_message_id chain (nested replies included) up to a
   * sane depth, oldest first, so the AI sees the full quoted conversation
   * a user built up without needing every message re-sent to it manually.
   *
   * @return \Illuminate\Support\Collection<int, Message>
   */
  private function collectReplyChainMessages(Message $message, int $maxDepth = 20): \Illuminate\Support\Collection
  {
    $chain = collect();
    $current = $message->replyTo()->with('user.profile')->first();
    $depth = 0;

    while ($current && $depth < $maxDepth) {
      $chain->prepend($current);
      $current = $current->replyTo()->with('user.profile')->first();
      $depth++;
    }

    return $chain->values();
  }

  /**
   * @return array{role: string, name: ?string, content: string}
   */
  private function toContext(Message $message): array
  {
    $isAi = (bool) optional($message->user)->is_ai;
    $name = $message->guest_name
      ?? optional(optional($message->user)->profile)->profile_name
      ?? optional($message->user)->username
      ?? 'Người dùng';

    return [
      'role' => $isAi ? 'assistant' : 'user',
      'name' => $isAi ? null : $name,
      // A recalled/unsent message's content is never actually deleted from
      // the DB (recall is a display-only flag, same as getMessages() hiding
      // it) - without this check it would otherwise leak straight into the
      // AI's context even though every other part of the app already treats
      // it as gone. Same reasoning as the media placeholder below: labeled,
      // not silently included or skipped, so the AI knows something was
      // there but can't see what. Only relevant for older messages further
      // back in a reply chain/history - the immediate reply target is
      // checked and refused outright in runAsk()/runSummary() instead.
      'content' => $message->is_recalled
        ? '[tin nhắn đã bị thu hồi]'
        : ($message->type === 'text'
          ? (string) $message->content
          : '[' . match ($message->type) {
            'image' => 'đã gửi một hình ảnh',
            'video' => 'đã gửi một video',
            'file' => 'đã gửi một tệp đính kèm',
            default => 'nội dung không phải văn bản',
          } . ']'),
    ];
  }

  private function stripCommandPrefix(string $content, string $command): string
  {
    $trimmed = trim($content);
    if (stripos($trimmed, $command) === 0) {
      return trim(substr($trimmed, strlen($command)));
    }

    return $trimmed;
  }
}
