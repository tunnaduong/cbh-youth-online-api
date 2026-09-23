<x-mail::message>
# {{ $contentType === 'comment' ? 'Có bình luận mới cần kiểm duyệt' : 'Có bài viết mới cần kiểm duyệt' }}

Xin chào **{{ $recipientName }},**

@if ($contentType === 'comment')
Một bình luận mới của **{{ $authorUsername ?? 'người dùng' }}** vừa được đưa vào hàng chờ kiểm duyệt và cần bạn xem xét.
@else
Một bài viết mới của **{{ $authorUsername ?? 'người dùng' }}** vừa được đưa vào hàng chờ kiểm duyệt và cần bạn xem xét.
@endif

@if ($reason)
**Lý do AI đánh dấu:** {{ $reason }}
@endif

<x-mail::button :url="$queueUrl">
Xem hàng chờ kiểm duyệt
</x-mail::button>

Trân trọng,

CYO - Diễn đàn học sinh Chuyên Biên Hòa.
</x-mail::message>
