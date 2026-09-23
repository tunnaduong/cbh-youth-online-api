<x-mail::message :unsubscribe-url="$unsubscribeUrl">
# {{ $contentType === 'comment' ? 'Bình luận của bạn đang chờ kiểm duyệt' : 'Bài viết của bạn đang chờ kiểm duyệt' }}

Thân gửi bạn **{{ $recipientName }},**

@if ($contentType === 'comment')
Bình luận của bạn trong bài viết **"{{ $topicTitle }}"** đang **CHỜ KIỂM DUYỆT**. Bình luận sẽ tạm thời chưa hiển thị với người khác cho đến khi được ban quản trị duyệt.
@else
Bài viết **"{{ $topicTitle }}"** của bạn đang **CHỜ KIỂM DUYỆT**. Bài viết sẽ tạm thời chưa hiển thị với người khác cho đến khi được ban quản trị duyệt.
@endif

@if ($reason)
**Lý do cần kiểm duyệt:** {{ $reason }}
@endif

Bạn vẫn xem được nội dung của mình và sẽ nhận được email ngay khi có kết quả.

<x-mail::button :url="$url">
Xem nội dung của bạn
</x-mail::button>

Mọi thắc mắc, vui lòng liên hệ:

- Fanpage: [https://www.facebook.com/cbhyouthonline](https://www.facebook.com/cbhyouthonline)
- Mail: hotro@chuyenbienhoa.com

Trân trọng,

CYO - Diễn đàn học sinh Chuyên Biên Hòa.
</x-mail::message>
