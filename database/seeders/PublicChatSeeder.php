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
    // Find existing public chat by its is_public flag (falls back to the legacy
    // name+type match, for databases that haven't backfilled is_public yet)
    $publicChat = Conversation::where('is_public', true)->first()
      ?? Conversation::where('name', 'Tán gẫu linh tinh')->where('type', 'group')->first();

    // If not found, create a new one
    if (!$publicChat) {
      $publicChat = Conversation::create([
        'type' => 'group',
        'name' => 'Tán gẫu linh tinh',
        'is_public' => true,
      ]);
    } else {
      // Ensure it's a group type, has the correct name, and is flagged public
      if ($publicChat->name !== 'Tán gẫu linh tinh' || $publicChat->type !== 'group' || !$publicChat->is_public) {
        $publicChat->update([
          'type' => 'group',
          'name' => 'Tán gẫu linh tinh',
          'is_public' => true,
        ]);
      }
    }
  }
}
