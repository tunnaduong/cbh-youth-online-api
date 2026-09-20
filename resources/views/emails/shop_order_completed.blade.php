<x-mail::message>
# Đơn hàng đã được giao! 🎉

Xin chào! Đơn hàng **#{{ $orderId }}** của bạn đã được giao thành công.

**Chi tiết đơn hàng:**

<x-mail::table>
| Sản phẩm | Số lượng | Thành tiền |
|:---------|:--------:|-----------:|
@foreach ($items as $item)
| {{ $item['name'] }}{{ $item['variant'] ? ' (' . $item['variant'] . ')' : '' }} | {{ $item['quantity'] }} | {{ $item['subtotal'] }}đ |
@endforeach
| **Tổng cộng** | | **{{ $total }}đ** |
</x-mail::table>

Cảm ơn bạn đã mua sắm cùng chúng tôi! Nếu có bất kỳ vấn đề gì, bạn có thể xem lại đơn hàng tại đây.

<x-mail::button :url="$ordersUrl">
Xem đơn hàng của tôi
</x-mail::button>

Trân trọng,<br>
{{ config('app.name') }}
</x-mail::message>
