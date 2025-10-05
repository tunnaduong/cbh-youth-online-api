<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  /**
   * Run the migrations.
   */
  public function up(): void
  {
    Schema::table('cyo_topics', function (Blueprint $table) {
      // Index cho privacy và hidden để tối ưu query feed
      $table->index(['privacy', 'hidden', 'created_at'], 'idx_topics_privacy_hidden_created');

      // Index cho user_id để tối ưu query theo user
      $table->index(['user_id', 'created_at'], 'idx_topics_user_created');

      // Index cho subforum_id để tối ưu query theo subforum
      $table->index(['subforum_id', 'created_at'], 'idx_topics_subforum_created');
    });

    Schema::table('cyo_topic_comments', function (Blueprint $table) {
      // Index cho topic_id để tối ưu query comments
      $table->index(['topic_id', 'created_at'], 'idx_comments_topic_created');

      // Index cho user_id để tối ưu query comments theo user
      $table->index(['user_id', 'created_at'], 'idx_comments_user_created');
    });

    Schema::table('cyo_topic_votes', function (Blueprint $table) {
      // Index cho topic_id để tối ưu query votes
      $table->index(['topic_id', 'created_at'], 'idx_votes_topic_created');

      // Index cho user_id để tối ưu query votes theo user
      $table->index(['user_id', 'created_at'], 'idx_votes_user_created');
    });

    Schema::table('cyo_online_users', function (Blueprint $table) {
      // Index cho last_activity để tối ưu cleanup
      $table->index('last_activity', 'idx_online_users_activity');

      // Index cho user_id và session_id
      $table->index(['user_id', 'session_id'], 'idx_online_users_user_session');
    });

    Schema::table('cyo_user_saved_topics', function (Blueprint $table) {
      // Index cho user_id để tối ưu query saved topics
      $table->index(['user_id', 'topic_id'], 'idx_saved_topics_user_topic');
    });

    Schema::table('cyo_auth_accounts', function (Blueprint $table) {
      // Index cho created_at để tối ưu query latest user
      $table->index('created_at', 'idx_auth_accounts_created');
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::table('cyo_topics', function (Blueprint $table) {
      $table->dropIndex('idx_topics_privacy_hidden_created');
      $table->dropIndex('idx_topics_user_created');
      $table->dropIndex('idx_topics_subforum_created');
    });

    Schema::table('cyo_topic_comments', function (Blueprint $table) {
      $table->dropIndex('idx_comments_topic_created');
      $table->dropIndex('idx_comments_user_created');
    });

    Schema::table('cyo_topic_votes', function (Blueprint $table) {
      $table->dropIndex('idx_votes_topic_created');
      $table->dropIndex('idx_votes_user_created');
    });

    Schema::table('cyo_online_users', function (Blueprint $table) {
      $table->dropIndex('idx_online_users_activity');
      $table->dropIndex('idx_online_users_user_session');
    });

    Schema::table('cyo_user_saved_topics', function (Blueprint $table) {
      $table->dropIndex('idx_saved_topics_user_topic');
    });

    Schema::table('cyo_auth_accounts', function (Blueprint $table) {
      $table->dropIndex('idx_auth_accounts_created');
    });
  }
};
