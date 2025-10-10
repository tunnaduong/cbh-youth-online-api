<?php

namespace App\Console\Commands;

use App\Models\TopicComment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixCommentTimestamps extends Command
{
  /**
   * The name and signature of the console command.
   *
   * @var string
   */
  protected $signature = 'comments:fix-timestamps';

  /**
   * The console command description.
   *
   * @var string
   */
  protected $description = 'Fix comment timestamps by setting updated_at to match created_at for comments that were only updated during migration';

  /**
   * Execute the console command.
   */
  public function handle()
  {
    $this->info('🔧 Fixing comment timestamps...');

    // Use raw SQL to find comments where updated_at differs from created_at
    // This handles precision and collation issues
    $affectedComments = DB::select("
            SELECT id, created_at, updated_at
            FROM cyo_topic_comments
            WHERE ABS(TIMESTAMPDIFF(MICROSECOND, created_at, updated_at)) > 0
        ");

    $this->info("Found " . count($affectedComments) . " comments with different timestamps");

    $count = 0;

    foreach ($affectedComments as $comment) {
      // Use raw SQL to update updated_at to match created_at exactly
      DB::update("
                UPDATE cyo_topic_comments
                SET updated_at = created_at
                WHERE id = ?
            ", [$comment->id]);

      $count++;
    }

    $this->info("✅ Fixed timestamps for {$count} comments");
    $this->info("All comments now show their original creation time instead of 'đã chỉnh sửa'");

    // Verify the fix
    $remaining = DB::select("
            SELECT COUNT(*) as count
            FROM cyo_topic_comments
            WHERE ABS(TIMESTAMPDIFF(MICROSECOND, created_at, updated_at)) > 0
        ");

    $this->info("Remaining comments with different timestamps: " . $remaining[0]->count);

    return 0;
  }
}
