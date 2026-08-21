<?php

namespace App\Console\Commands;

use App\Jobs\ProcessUserContentVideoPreview;
use App\Jobs\ProcessVideoPreview;
use App\Models\Message;
use App\Models\TopicComment;
use App\Models\UserContent;
use App\Services\MediaThumbnailService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * One-off backfill for the thumbnail/preview fields added after chat, posts,
 * and comments already had real content in them (see MediaThumbnailService) -
 * nothing generates these retroactively on its own, so anything uploaded
 * before that change just permanently falls back to the full-resolution
 * original in the frontend. This walks the three places that got the new
 * fields and fills in whatever's missing.
 *
 * Video preview clips are a real ffmpeg transcode (slow, one per video), so
 * those are queued (ProcessVideoPreview/ProcessUserContentVideoPreview) same
 * as new uploads already are, rather than run inline here. Image thumbnails
 * and video first-frames are cheap enough to just generate synchronously as
 * the command runs.
 */
class BackfillMediaThumbnails extends Command
{
  protected $signature = 'media:backfill-thumbnails
    {--dry-run : Report what would be generated without writing/dispatching anything}
    {--limit= : Only process up to this many rows per category (default: all)}';

  protected $description = 'Generate missing thumbnail/preview media for chat messages, posts/stories, and comments uploaded before that feature existed';

  public function handle(): int
  {
    $dryRun = (bool) $this->option('dry-run');
    $limit = $this->option('limit') ? (int) $this->option('limit') : null;

    if ($dryRun) {
      $this->warn('Dry run - nothing will be written or queued.');
    }

    $this->backfillChatMessages($dryRun, $limit);
    $this->newLine();
    $this->backfillUserContent($dryRun, $limit);
    $this->newLine();
    $this->backfillComments($dryRun, $limit);

    return self::SUCCESS;
  }

  private function backfillChatMessages(bool $dryRun, ?int $limit): void
  {
    $this->info('Chat messages');

    $query = Message::whereIn('type', ['image', 'video'])
      ->whereNotNull('file_url')
      ->where('is_recalled', false)
      ->orderBy('id');

    $total = (clone $query)->count();
    $processed = 0;
    $generated = 0;
    $skippedExisting = 0;
    $failed = 0;

    $query->chunkById(200, function ($messages) use (&$processed, &$generated, &$skippedExisting, &$failed, $dryRun, $limit) {
      foreach ($messages as $message) {
        if ($limit !== null && $processed >= $limit) {
          return false;
        }
        $processed++;

        $metadata = $message->metadata ?? [];
        $needsThumbnail = empty($metadata['thumbnail_url']);
        $needsPreview = $message->type === 'video' && empty($metadata['preview_url']);

        if (!$needsThumbnail && !$needsPreview) {
          $skippedExisting++;
          continue;
        }

        if ($dryRun) {
          $generated++;
          continue;
        }

        try {
          if ($needsThumbnail) {
            $thumbPath = $message->type === 'video'
              ? MediaThumbnailService::videoFirstFrame($message->file_url, 'chat_files/video-frames')
              : MediaThumbnailService::imageThumbnail($message->file_url, 'chat_files/image-thumbs');

            if ($thumbPath) {
              $metadata['thumbnail_url'] = MediaThumbnailService::absoluteUrl($thumbPath);
              $message->update(['metadata' => $metadata]);
            }
          }

          if ($needsPreview) {
            ProcessVideoPreview::dispatch($message->file_url, $message->id);
          }

          $generated++;
        } catch (\Throwable $e) {
          $failed++;
          $this->error("  Message #{$message->id}: {$e->getMessage()}");
        }
      }

      return true;
    });

    $this->line("  {$total} candidates, {$processed} processed, {$generated} generated/queued, {$skippedExisting} already had one, {$failed} failed.");
  }

  private function backfillUserContent(bool $dryRun, ?int $limit): void
  {
    $this->info('Posts/stories (UserContent)');

    $query = UserContent::where(function ($q) {
      $q->where('file_type', 'like', 'image/%')
        ->orWhere('file_type', 'like', 'video/%');
    })->orderBy('id');

    $total = (clone $query)->count();
    $processed = 0;
    $generated = 0;
    $skippedExisting = 0;
    $failed = 0;

    $query->chunkById(200, function ($rows) use (&$processed, &$generated, &$skippedExisting, &$failed, $dryRun, $limit) {
      foreach ($rows as $content) {
        if ($limit !== null && $processed >= $limit) {
          return false;
        }
        $processed++;

        $isVideo = str_starts_with($content->file_type ?? '', 'video/');
        $needsThumbnail = empty($content->thumbnail_path);
        $needsPreview = $isVideo && empty($content->preview_path);

        if (!$needsThumbnail && !$needsPreview) {
          $skippedExisting++;
          continue;
        }

        if ($dryRun) {
          $generated++;
          continue;
        }

        try {
          if ($needsThumbnail) {
            $thumbPath = $isVideo
              ? MediaThumbnailService::videoFirstFrame($content->file_path)
              : MediaThumbnailService::imageThumbnail($content->file_path);

            if ($thumbPath) {
              $content->update(['thumbnail_path' => $thumbPath]);
            }
          }

          if ($needsPreview) {
            ProcessUserContentVideoPreview::dispatch($content->file_path, $content->id);
          }

          $generated++;
        } catch (\Throwable $e) {
          $failed++;
          $this->error("  UserContent #{$content->id}: {$e->getMessage()}");
        }
      }

      return true;
    });

    $this->line("  {$total} candidates, {$processed} processed, {$generated} generated/queued, {$skippedExisting} already had one, {$failed} failed.");
  }

  private function backfillComments(bool $dryRun, ?int $limit): void
  {
    $this->info('Comments');

    $query = TopicComment::whereNotNull('image_urls')->orderBy('id');

    $total = (clone $query)->count();
    $processed = 0;
    $generated = 0;
    $skippedExisting = 0;
    $failed = 0;

    $query->chunkById(200, function ($comments) use (&$processed, &$generated, &$skippedExisting, &$failed, $dryRun, $limit) {
      foreach ($comments as $comment) {
        if ($limit !== null && $processed >= $limit) {
          return false;
        }
        $processed++;

        $imagePaths = $comment->image_urls ?? [];
        $existingThumbs = $comment->image_thumbnail_urls ?? [];

        // Only regenerate the holes (index-paired with image_urls), not
        // images that already have a thumbnail from a previous run.
        $missingAny = false;
        foreach ($imagePaths as $i => $path) {
          if (empty($existingThumbs[$i] ?? null)) {
            $missingAny = true;
            break;
          }
        }

        if (!$missingAny) {
          $skippedExisting++;
          continue;
        }

        if ($dryRun) {
          $generated++;
          continue;
        }

        try {
          $newThumbs = $existingThumbs;
          foreach ($imagePaths as $i => $path) {
            if (!empty($newThumbs[$i] ?? null)) {
              continue;
            }
            $thumbPath = MediaThumbnailService::imageThumbnail($path, 'comment_images/thumbs');
            $newThumbs[$i] = $thumbPath;
          }
          $comment->update(['image_thumbnail_urls' => $newThumbs]);
          $generated++;
        } catch (\Throwable $e) {
          $failed++;
          $this->error("  Comment #{$comment->id}: {$e->getMessage()}");
        }
      }

      return true;
    });

    $this->line("  {$total} candidates, {$processed} processed, {$generated} generated/queued, {$skippedExisting} already had one, {$failed} failed.");
  }
}
