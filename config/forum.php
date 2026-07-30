<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Subforum "Tin tức Đoàn"
    |--------------------------------------------------------------------------
    |
    | ID của subforum Tin tức Đoàn. Bài viết thuộc subforum này được hiển thị
    | riêng ở trang Tin tức Đoàn nên bị loại khỏi các bảng xếp hạng chung của
    | diễn đàn (mới nhất, xem nhiều nhất, tương tác nhiều nhất).
    |
    | Dùng App\Models\Topic::scopeExcludingNewsSubforum() /
    | scopeInNewsSubforum() thay vì đọc trực tiếp giá trị này.
    |
    */

    'news_subforum_id' => env('FORUM_NEWS_SUBFORUM_ID', 32),
];
