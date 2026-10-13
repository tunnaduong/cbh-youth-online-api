<?php

namespace App\Jobs;

use App\Models\Topic;
use App\Services\TopicCoverService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateTopicCover implements ShouldQueue
{
  use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

  public int $timeout = 120;
  public int $tries = 2;

  public function __construct(private int $topicId) {}

  public function handle(TopicCoverService $covers): void
  {
    $topic = Topic::find($this->topicId);
    if (!$topic) {
      return;
    }

    try {
      $covers->generate($topic);
    } catch (\Throwable $e) {
      Log::error('GenerateTopicCover failed', ['topic' => $this->topicId, 'error' => $e->getMessage()]);
    }
  }
}
