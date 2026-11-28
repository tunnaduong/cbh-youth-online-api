<?php

namespace Tests\Unit;

use App\Models\Topic;
use App\Services\TopicCoverService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GenerateTopicCoversTest extends TestCase
{
  protected function setUp(): void
  {
    parent::setUp();

    config([
      'database.default' => 'sqlite',
      'database.connections.sqlite.database' => ':memory:',
    ]);
    DB::purge('sqlite');

    Schema::create('cyo_topics', function (Blueprint $table) {
      $table->unsignedBigInteger('id')->primary();
      $table->unsignedBigInteger('user_id')->default(1);
      $table->string('title')->default('');
      $table->text('description')->nullable();
      $table->text('cdn_image_id')->nullable();
      $table->text('cdn_video_id')->nullable();
      $table->string('cover_image')->nullable();
      $table->timestamps();
    });

    Schema::create('cyo_cdn_user_content', function (Blueprint $table) {
      $table->unsignedBigInteger('id')->primary();
      $table->unsignedBigInteger('user_id')->default(1);
      $table->string('file_name')->default('test.jpg');
      $table->string('file_path');
      $table->string('file_type')->default('image/jpeg');
      $table->unsignedBigInteger('file_size')->default(100);
      $table->timestamps();
    });

    Storage::fake('public');
  }

  public function test_generates_cover_when_cdn_image_files_are_missing_on_disk(): void
  {
    DB::table('cyo_cdn_user_content')->insert([
      'id' => 688,
      'user_id' => 1,
      'file_name' => 'missing.jpg',
      'file_path' => 'images/missing.jpg',
    ]);

    DB::table('cyo_topics')->insert([
      'id' => 772511051,
      'user_id' => 1,
      'title' => 'Review Panasonic cf xz6',
      'description' => 'Detailed review body.',
      'cdn_image_id' => '688',
      'cover_image' => null,
    ]);

    $this->artisan('topics:generate-covers', ['--id' => ['772511051']])
      ->expectsOutputToContain('#772511051 -> covers/topic-772511051-')
      ->expectsOutputToContain('Done: 1 covers, 0 skipped.')
      ->assertExitCode(0);

    $topic = Topic::find(772511051);
    $this->assertNotNull($topic->cover_image);
    Storage::disk('public')->assertExists($topic->cover_image);
    $this->assertCount(0, $topic->getImageUrls());
  }

  public function test_generates_cover_for_deleted_user_content_and_malformed_cdn_image_id(): void
  {
    DB::table('cyo_topics')->insert([
      [
        'id' => 913585209,
        'user_id' => 1,
        'title' => 'Deleted UserContent row',
        'description' => 'Some body',
        'cdn_image_id' => '133',
        'cover_image' => null,
      ],
      [
        'id' => 913585210,
        'user_id' => 1,
        'title' => 'Malformed cdn_image_id',
        'description' => 'Some body',
        'cdn_image_id' => ' , ',
        'cover_image' => '',
      ],
    ]);

    $this->artisan('topics:generate-covers', ['--id' => ['913585209,913585210']])
      ->expectsOutputToContain('Done: 2 covers, 0 skipped.')
      ->assertExitCode(0);

    $this->assertNotNull(Topic::find(913585209)->cover_image);
    $this->assertNotNull(Topic::find(913585210)->cover_image);
  }

  public function test_skips_topics_with_existing_photo_file_on_disk(): void
  {
    Storage::disk('public')->put('images/real.jpg', 'fake-jpeg-bytes');

    DB::table('cyo_cdn_user_content')->insert([
      'id' => 500,
      'user_id' => 1,
      'file_name' => 'real.jpg',
      'file_path' => 'images/real.jpg',
    ]);

    DB::table('cyo_topics')->insert([
      [
        'id' => 1001,
        'user_id' => 1,
        'title' => 'Has real photo',
        'description' => 'Body',
        'cdn_image_id' => '500',
        'cover_image' => null,
      ],
      [
        'id' => 1002,
        'user_id' => 1,
        'title' => 'Text only post',
        'description' => 'Body',
        'cdn_image_id' => null,
        'cover_image' => null,
      ],
    ]);

    // Explicit --id explains why it was skipped
    $this->artisan('topics:generate-covers', ['--id' => ['1001']])
      ->expectsOutputToContain('#1001: skipped (topic already has photo)')
      ->expectsOutputToContain('Done: 0 covers, 1 skipped.')
      ->assertExitCode(0);

    // Bulk run silently skips photo topics and does not count them against --limit
    $this->artisan('topics:generate-covers', ['--limit' => 1])
      ->expectsOutputToContain('#1002 -> covers/topic-1002-')
      ->expectsOutputToContain('Done: 1 covers, 0 skipped.')
      ->assertExitCode(0);

    $this->assertNull(Topic::find(1001)->cover_image);
    $this->assertNotNull(Topic::find(1002)->cover_image);
    $this->assertTrue(TopicCoverService::hasUsablePhoto(Topic::find(1001)));
    $this->assertFalse(TopicCoverService::hasUsablePhoto(Topic::find(1002)));
  }
}
