<x-mail::message :unsubscribe-url="$unsubscribeUrl">
# {{ $contentType === 'comment' ? 'Bình luận của bạn không được duyệt' : 'Bài viết của bạn không được duyệt' }}

Thân gửi bạn **{{ $recipientName }},**

@if ($contentType === 'comment')
Bình luận của bạn trong bài viết **"{{ $topicTitle }}"** đã được ban quản trị xem xét và **KHÔNG ĐƯỢC DUYỆT**. Bình luận sẽ không hiển thị công khai trên diễn đàn.
@else
Bài viết **"{{ $topicTitle }}"** của bạn đã được ban quản trị xem xét và **KHÔNG ĐƯỢC DUYỆT**. Bài viết sẽ không hiển thị công khai trên diễn đàn.
@endif

@if ($reason)
**Lý do:** {{ $reason }}
@endif

Bạn có thể xem lại [Nội quy diễn đàn](https://chuyenbienhoa.com/policy/forum-rules) trước khi đăng nội dung mới. Nếu cho rằng đây là nhầm lẫn, hãy liên hệ với chúng tôi.

Mọi thắc mắc, vui lòng liên hệ:

- Fanpage: [https://www.facebook.com/cbhyouthonline](https://www.facebook.com/cbhyouthonline)
- Mail: hotro@chuyenbienhoa.com

Trân trọng,

CYO - Diễn đàn học sinh Chuyên Biên Hòa.
</x-mail::message>
