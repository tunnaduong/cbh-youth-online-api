<x-mail::message :unsubscribe-url="$unsubscribeUrl">
# Bản tin diễn đàn tuần này

Xin chào {{ $recipient->username }}, đây là những bài viết đáng chú ý dành cho bạn.

@foreach ($topics as $topic)
## {{ $topic['title'] }}

{{ $topic['excerpt'] }}

{{ $topic['vote_score'] }} điểm vote | {{ $topic['comments_count'] }} bình luận

<x-mail::button :url="$baseUrl . '/' . $topic['author_username'] . '/posts/' . $topic['id']">
Đọc bài viết
</x-mail::button>

@endforeach

Trân trọng,<br>
{{ config('app.name') }}
</x-mail::message>