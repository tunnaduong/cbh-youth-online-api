<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Backfill is_anonymous for comment_liked / comment_downvoted
        // Join notification data->comment_id with cyo_topic_comments.is_anonymous
        DB::statement("
            UPDATE cyo_notifications n
            JOIN cyo_topic_comments c
                ON c.id = JSON_UNQUOTE(JSON_EXTRACT(n.data, '$.comment_id'))
            SET n.data = JSON_SET(n.data, '$.is_anonymous', IF(c.is_anonymous = 1, CAST('true' AS JSON), CAST('false' AS JSON)))
            WHERE n.type IN ('comment_liked', 'comment_downvoted')
              AND JSON_EXTRACT(n.data, '$.comment_id') IS NOT NULL
        ");

        // Backfill is_anonymous for topic_commented
        // Join notification data->comment_id with cyo_topic_comments.is_anonymous
        DB::statement("
            UPDATE cyo_notifications n
            JOIN cyo_topic_comments c
                ON c.id = JSON_UNQUOTE(JSON_EXTRACT(n.data, '$.comment_id'))
            SET n.data = JSON_SET(n.data, '$.is_anonymous', IF(c.is_anonymous = 1, CAST('true' AS JSON), CAST('false' AS JSON)))
            WHERE n.type = 'topic_commented'
              AND JSON_EXTRACT(n.data, '$.comment_id') IS NOT NULL
        ");

        // Backfill is_anonymous for comment_replied
        // Join notification data->reply_id with cyo_topic_comments.is_anonymous
        DB::statement("
            UPDATE cyo_notifications n
            JOIN cyo_topic_comments c
                ON c.id = JSON_UNQUOTE(JSON_EXTRACT(n.data, '$.reply_id'))
            SET n.data = JSON_SET(n.data, '$.is_anonymous', IF(c.is_anonymous = 1, CAST('true' AS JSON), CAST('false' AS JSON)))
            WHERE n.type = 'comment_replied'
              AND JSON_EXTRACT(n.data, '$.reply_id') IS NOT NULL
        ");

        // Backfill is_anonymous for mentioned (when mention is inside a comment)
        DB::statement("
            UPDATE cyo_notifications n
            JOIN cyo_topic_comments c
                ON c.id = JSON_UNQUOTE(JSON_EXTRACT(n.data, '$.comment_id'))
            SET n.data = JSON_SET(n.data, '$.is_anonymous', IF(c.is_anonymous = 1, CAST('true' AS JSON), CAST('false' AS JSON)))
            WHERE n.type = 'mentioned'
              AND JSON_EXTRACT(n.data, '$.comment_id') IS NOT NULL
        ");
    }

    public function down(): void
    {
        // Remove the is_anonymous field from affected notification types
        DB::statement("
            UPDATE cyo_notifications
            SET data = JSON_REMOVE(data, '$.is_anonymous')
            WHERE type IN ('comment_liked', 'comment_downvoted', 'topic_commented', 'comment_replied', 'mentioned')
        ");
    }
};
