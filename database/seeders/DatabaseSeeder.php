<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
  /**
   * Seed the application's database.
   */
  public function run(): void
  {
    // Phase 1: Base Tables (No Dependencies)
    $this->call([
      AuthAccountsSeeder::class,
      ForumMainCategoriesSeeder::class,
      SchoolTeachersSeeder::class,
      SchoolMistakeListSeeder::class,
      OnlineRecordSeeder::class,
      StudyMaterialCategorySeeder::class,
    ]);

    // Phase 2: First-Level Dependencies
    $this->call([
      UserProfilesSeeder::class,
      AuthEmailVerificationCodeSeeder::class,
      CdnUserContentSeeder::class,
      NotificationSettingsSeeder::class,
      NotificationSubscriptionsSeeder::class,
      OnlineUsersSeeder::class,
      ForumSubforumsSeeder::class,
      SchoolClassesSeeder::class,
      ConversationsSeeder::class,
    ]);

    // Phase 3: Second-Level Dependencies
    $this->call([
      SchoolStudentsSeeder::class,
      VolunteersSeeder::class,
      RecordingsSeeder::class,
      TopicsSeeder::class,
      StoriesSeeder::class,
      ConversationParticipantsSeeder::class,
      StudyMaterialSeeder::class,
    ]);

    // Phase 4: Third-Level Dependencies
    $this->call([
      TopicCommentsSeeder::class,
      TopicVotesSeeder::class,
      TopicViewsSeeder::class,
      TopicCommentVotesSeeder::class,
      UserSavedTopicsSeeder::class,
      UserFollowersSeeder::class,
      StoryViewersSeeder::class,
      StoryReactionsSeeder::class,
      ConversationMessagesSeeder::class,
      NotificationsSeeder::class,
      RecordingViewsSeeder::class,
      UserReportsSeeder::class,
      UserPointDeductionsSeeder::class,
      SchoolTimetablesSeeder::class,
      SchoolNewsSeeder::class,
      SchoolMonthlyRankingSeeder::class,
      VolunteerDailyReportsSeeder::class,
    ]);
  }
}
