<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\AuthAccount;
use Carbon\Carbon;

class PublicChatMessagesSeeder extends Seeder
{
  /**
   * Run the database seeds.
   */
  public function run(): void
  {
    // Find or create public chat conversation
    $publicChat = Conversation::where('name', 'Tán gẫu linh tinh')
      ->where('type', 'group')
      ->first();

    if (!$publicChat) {
      $publicChat = Conversation::create([
        'type' => 'group',
        'name' => 'Tán gẫu linh tinh',
      ]);
    }

    // Parse and create messages
    $messages = [
      // Format: [date_string, guest_name_or_username, content]
      ['Mon Apr 21, 5:09:29pm', 'anon2111', 'Hello, có ai khônggg? Tôi cô đơn quá...'],
      ['Fri Apr 25, 10:53:34am', 'anon0767', 'hi'],
      ['Fri Apr 25, 10:54:52am', 'anon1750', 'hi'],
      ['Fri Apr 25, 10:55:19am', 'anon1750', 'cuối cùng cũng có người :>'],
      ['Mon Apr 28, 10:39:49pm', 'Myeyesdeceive', 'Chào mn'], // User, not guest
      ['Thu May 1, 12:49:33pm', 'anon6161', 'hê lô b'],
      ['Fri May 2, 2:46:41pm', 'anon5543', 'hui'],
      ['Sat May 3, 4:08:37pm', 'anon0445', 'Yo'],
      ['Sat May 3, 4:08:45pm', 'anon9155', 'Yo'],
      ['Sun May 4, 5:53:44pm', 'anon5966', 'Sex'],
      ['Sun May 4, 5:53:54pm', 'anon5966', 'Scat porn'],
      ['Mon May 5, 11:43:00am', 'anon7261', 'Skibidi'],
      ['Mon May 5, 11:43:06am', 'anon7261', ''],
      ['Thu May 8, 7:59:34pm', 'anon0183', 'Oắt phắc  @anon5966'],
      ['Sun May 18, 10:58:01am', 'anon7758', 'J v má @anon5966'],
      ['Sat May 24, 10:52:22am', 'anon7842', 'Hê lô anh em'],
      ['Sat May 24, 10:52:30am', 'anon7842', 'Có anh em nào ở 12 địa không'],
      ['Mon May 26, 8:52:11am', 'anon2988', '16 Nga chào anh em nhé'],
      ['Thu June 26, 2:04:40pm', 'anon6849', 'giờ trường có cả lớp 16 ???'],
      ['Thu June 26, 6:04:28pm', 'anon2558', 'Trêu thế chứ mình K60'],
      ['Tue July 1, 10:47:47am', 'Myeyesdeceive', 'Chào các ac ạ, em k67 văn (´∩｡• ᵕ •｡∩`)'],
    ];

    foreach ($messages as $messageData) {
      [$dateString, $name, $content] = $messageData;

      // Parse date string to Carbon instance
      // Format: "Mon Apr 21, 5:09:29pm" (abbreviated month) or "Thu June 26, 2:04:40pm" (full month)
      $year = date('Y'); // Current year
      $parsedDate = null;

      // Try different formats
      $formats = [
        'D F d, g:i:s A',  // Full month with seconds: "Thu June 26, 2:04:40pm"
        'D M d, g:i:s A',  // Abbreviated month with seconds: "Mon Apr 21, 5:09:29pm"
        'D F d, g:i A',    // Full month without seconds: "Thu June 26, 2:04pm"
        'D M d, g:i A',    // Abbreviated month without seconds: "Mon Apr 21, 5:09pm"
      ];

      foreach ($formats as $format) {
        try {
          $parsedDate = Carbon::createFromFormat($format, $dateString);
          $parsedDate->setYear($year);

          // If the date is in the future (meaning it should be last year), subtract a year
          if ($parsedDate->isFuture()) {
            $parsedDate->subYear();
          }
          break; // Successfully parsed
        } catch (\Exception $e) {
          continue; // Try next format
        }
      }

      // If all formats failed, use current time as fallback
      if (!$parsedDate) {
        $parsedDate = Carbon::now();
        $this->command->warn("Failed to parse date: $dateString, using current time");
      }

      // Check if this is a guest (starts with 'anon') or a user
      $isGuest = str_starts_with($name, 'anon');
      $userId = null;
      $guestName = null;

      if ($isGuest) {
        $guestName = $name;
      } else {
        // Find user by username
        $user = AuthAccount::where('username', $name)->first();
        if ($user) {
          $userId = $user->id;
        } else {
          // If user doesn't exist, treat as guest
          $guestName = $name;
        }
      }

      // Create message
      Message::create([
        'conversation_id' => $publicChat->id,
        'user_id' => $userId,
        'guest_name' => $guestName,
        'content' => $content,
        'type' => 'text',
        'is_edited' => false,
        'created_at' => $parsedDate,
        'updated_at' => $parsedDate,
      ]);
    }

    $this->command->info('Inserted ' . count($messages) . ' public chat messages.');
  }
}

