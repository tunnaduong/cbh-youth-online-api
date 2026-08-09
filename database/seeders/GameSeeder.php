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
        'description' => 'Chơi ngay trong trình duyệt của bạn!',
        'category' => 'adventure',
        'image_url' => 'https://dhp2010.is-a.dev/165921312.jpeg',
        'iframe_url' => 'https://dhp2010.is-a.dev/minecraft',
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
        'iframe_url' => 'https://games.cdn.famobi.com/html5games/o/om-nom-run/v1240/?fg_domain=play.famobi.com&fg_aid=A1000-111&fg_uid=abe80572-560a-444d-baf7-2fa4a7b2c02f&fg_pid=e37ab3ce-88cd-4438-9b9c-a37df5d33736&fg_beat=490&original_ref=https%3A%2F%2Fplay.famobi.com%2Fom-nom-run',
        'platform' => 'both',
        'sort_order' => 2,
      ],
      [
        'name' => '3D Free Kick',
        'slug' => '3d-free-kick',
        'description' => 'Thực hiện những cú sút phạt đẳng cấp trong không gian 3D.',
        'category' => 'racing',
        'image_url' => 'https://img.cdn.famobi.com/portal/html5games/images/tmp/3dFreeKickTeaser.jpg?v=0.2-b6f14a96',
        'iframe_url' => 'https://games.cdn.famobi.com/html5games/0/3d-free-kick/16498237/?fg_domain=play.famobi.com&fg_aid=A1000-111&fg_uid=2ee096ab-4cd7-4f9a-baa9-f58a54413c47&fg_pid=e37ab3ce-88cd-4438-9b9c-a37df5d33736&fg_beat=491&original_ref=https%3A%2F%2Fplay.famobi.com%2F3d-free-kick',
        'platform' => 'both',
        'sort_order' => 3,
      ],
      [
        'name' => '8 Ball Billiards Classic',
        'slug' => '8-ball-billiards-classic',
        'description' => 'Chơi bida 8 bi cổ điển, tính toán đường bi thật chuẩn.',
        'category' => 'puzzle',
        'image_url' => 'https://img.cdn.famobi.com/portal/html5games/images/tmp/8BallBilliardsClassicTeaser.jpg?v=0.2-b6f14a96',
        'iframe_url' => 'https://games.cdn.famobi.com/html5games/0/8-ball-billiards-classic/f6589445/?fg_domain=play.famobi.com&fg_aid=A1000-111&fg_uid=82038e98-d4e1-46dd-8de9-1460d1395eab&fg_pid=e37ab3ce-88cd-4438-9b9c-a37df5d33736&fg_beat=490&original_ref=https%3A%2F%2Fplay.famobi.com%2F8-ball-billiards-classic',
        'platform' => 'both',
        'sort_order' => 4,
      ],
    ];

    foreach ($games as $game) {
      Game::updateOrCreate(['slug' => $game['slug']], $game);
    }
  }
}
