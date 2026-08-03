<?php

namespace App\Services;

use App\Models\Message;
use App\Models\Topic;
use App\Models\TopicComment;

class MentionService
{
  /**
   * Rewrite every stored @mention of $oldUsername to $newUsername across
   * posts, comments, and chat messages, so renaming an account doesn't
   * silently break mentions that were valid before the rename.
   */
  public static function renameMentions(string $oldUsername, string $newUsername): void
  {
    if ($oldUsername === '' || $oldUsername === $newUsername) {
      return;
    }

    $pattern = '/@' . preg_quote($oldUsername, '/') . '(?![\w-])/';
    $replacement = '@' . $newUsername;

    self::renameInModel(Topic::class, ['description', 'content_html'], $oldUsername, $pattern, $replacement);
    self::renameInModel(TopicComment::class, ['comment', 'comment_html'], $oldUsername, $pattern, $replacement);
    self::renameInModel(Message::class, ['content'], $oldUsername, $pattern, $replacement);
  }

  private static function renameInModel(string $modelClass, array $fields, string $oldUsername, string $pattern, string $replacement): void
  {
    $needle = '@' . $oldUsername;

    $query = $modelClass::query()->where(function ($q) use ($fields, $needle) {
      foreach ($fields as $field) {
        $q->orWhere($field, 'like', '%' . $needle . '%');
      }
    });

    $query->chunkById(200, function ($rows) use ($fields, $pattern, $replacement) {
      foreach ($rows as $row) {
        $dirty = false;

        foreach ($fields as $field) {
          $value = $row->{$field};
          if ($value === null) {
            continue;
          }

          $updated = preg_replace($pattern, $replacement, $value);
          if ($updated !== $value) {
            $row->{$field} = $updated;
            $dirty = true;
          }
        }

        if ($dirty) {
          // Renaming a mention shouldn't bump updated_at (which would make
          // old comments/messages/posts look freshly edited).
          $row->timestamps = false;
          $row->save();
        }
      }
    });
  }
}
