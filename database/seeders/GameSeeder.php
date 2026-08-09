<?php

namespace Database\Seeders;

use App\Models\Game;
use Illuminate\Database\Seeder;

class GameSeeder extends Seeder
{
  public function run(): void
  {
    $games = [
      [
        'name' => 'Minecraft',
        'slug' => 'minecraft',
        'description' => 'Minecraft chơi trực tiếp trên trình duyệt.',
        'category' => 'adventure',
        'image_url' => 'https://api.chuyenbienhoa.com/storage/games/minecraft.jpg',
        'iframe_url' => 'https://dhp2010.is-a.dev/minecraftnewer',
        // Needs mouse + keyboard controls - not playable on a touchscreen.
        'platform' => 'pc',
        'sort_order' => 1,
      ],
      [
        'name' => 'Om Nom Run',
        'slug' => 'om-nom-run',
        'description' => 'Chạy và thu thập kẹo cùng Om Nom trên hành trình vô tận.',
        'category' => 'arcade',
        'image_url' => 'https://img.cdn.famobi.com/portal/html5games/images/tmp/OmNomRunTeaser.jpg?v=0.2-b6f14a96',
        'iframe_url' => 'https://play.famobi.com/om-nom-run',
        'platform' => 'both',
        'sort_order' => 2,
      ],
      [
        'name' => '3D Free Kick',
        'slug' => '3d-free-kick',
        'description' => 'Thực hiện những cú sút phạt đẳng cấp trong không gian 3D.',
        'category' => 'racing',
        'image_url' => 'https://img.cdn.famobi.com/portal/html5games/images/tmp/3dFreeKickTeaser.jpg?v=0.2-b6f14a96',
        'iframe_url' => 'https://play.famobi.com/3d-free-kick',
        'platform' => 'both',
        'sort_order' => 3,
      ],
      [
        'name' => '8 Ball Billiards Classic',
        'slug' => '8-ball-billiards-classic',
        'description' => 'Chơi bida 8 bi cổ điển, tính toán đường bi thật chuẩn.',
        'category' => 'puzzle',
        'image_url' => 'https://img.cdn.famobi.com/portal/html5games/images/tmp/8BallBilliardsClassicTeaser.jpg?v=0.2-b6f14a96',
        'iframe_url' => 'https://play.famobi.com/8-ball-billiards-classic',
        'platform' => 'both',
        'sort_order' => 4,
      ],
    ];

    foreach ($games as $game) {
      Game::updateOrCreate(['slug' => $game['slug']], $game);
    }
  }
}
