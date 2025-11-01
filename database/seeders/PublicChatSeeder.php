<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Conversation;

class PublicChatSeeder extends Seeder
{
  /**
   * Run the database seeds.
   */
  public function run(): void
  {
    // Find existing public chat by name and type
    $publicChat = Conversation::where('name', 'Tán gẫu linh tinh')
      ->where('type', 'group')
      ->first();

    // If not found, create a new one
    if (!$publicChat) {
      $publicChat = Conversation::create([
        'type' => 'group',
        'name' => 'Tán gẫu linh tinh',
      ]);
    } else {
      // Ensure it's a group type and has the correct name
      if ($publicChat->name !== 'Tán gẫu linh tinh' || $publicChat->type !== 'group') {
        $publicChat->update([
          'type' => 'group',
          'name' => 'Tán gẫu linh tinh',
        ]);
      }
    }
  }
}
