<x-mail::message :unsubscribe-url="$unsubscribeUrl">
# {{ $contentType === 'comment' ? 'Bình luận của bạn đã được duyệt' : 'Bài viết của bạn đã được duyệt' }}

Thân gửi bạn **{{ $recipientName }},**

@if ($contentType === 'comment')
Bình luận của bạn trong bài viết **"{{ $topicTitle }}"** đã được kiểm duyệt và **DUYỆT THÀNH CÔNG**. Bình luận của bạn hiện đã hiển thị công khai trên diễn đàn.
@else
Bài viết **"{{ $topicTitle }}"** của bạn đã được kiểm duyệt và **DUYỆT THÀNH CÔNG**. Bài viết của bạn hiện đã hiển thị công khai trên diễn đàn.
@endif

<x-mail::button :url="$url">
Xem trên diễn đàn
</x-mail::button>

Mọi thắc mắc, vui lòng liên hệ:

- Fanpage: [https://www.facebook.com/cbhyouthonline](https://www.facebook.com/cbhyouthonline)
- Mail: hotro@chuyenbienhoa.com

Trân trọng,

CYO - Diễn đàn học sinh Chuyên Biên Hòa.
</x-mail::message>
