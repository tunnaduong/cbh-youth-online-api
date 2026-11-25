<?php

namespace Tests\Feature;

use App\Models\AuthAccount;
use App\Models\Call;
use App\Models\Conversation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CallSignalTest extends TestCase
{
    use RefreshDatabase;

    private function makeCall(string $type = 'video', string $status = 'accepted'): array
    {
        $caller = AuthAccount::factory()->create();
        $receiver = AuthAccount::factory()->create();
        $conversation = Conversation::create(['type' => 'private', 'created_by' => $caller->id]);
        $conversation->participants()->attach([$caller->id, $receiver->id]);
        $call = Call::create([
            'conversation_id' => $conversation->id,
            'caller_id' => $caller->id,
            'receiver_id' => $receiver->id,
            'type' => $type,
            'status' => $status,
        ]);

        return [$call, $caller, $receiver];
    }

    private function url(Call $call): string
    {
        return "/api/v1.0/chat/calls/{$call->id}/signal";
    }

    public function test_participant_can_send_video_state(): void
    {
        [$call, $caller] = $this->makeCall();
        Sanctum::actingAs($caller);

        $this->postJson($this->url($call), ['type' => 'video_state', 'payload' => ['enabled' => false]])
            ->assertOk();

        $signals = Cache::get("call_signals:{$call->id}", []);
        $this->assertSame('video_state', $signals[0]['type']);
        $this->assertNotNull(Cache::get("call_video_state:{$call->id}:{$caller->id}"));
    }

    public function test_audio_chunk_type_is_accepted(): void
    {
        [$call, $caller] = $this->makeCall('audio');
        Sanctum::actingAs($caller);

        $this->postJson($this->url($call), ['type' => 'audio_chunk', 'payload' => ['d' => 'AAAA']])
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_stranger_cannot_inject_signals(): void
    {
        [$call] = $this->makeCall();
        Sanctum::actingAs(AuthAccount::factory()->create());

        $this->postJson($this->url($call), ['type' => 'answer', 'payload' => ['type' => 'answer', 'sdp' => 'x']])
            ->assertStatus(403);
        $this->assertEmpty(Cache::get("call_signals:{$call->id}", []));
    }

    public function test_video_state_payload_must_be_boolean(): void
    {
        [$call, $caller] = $this->makeCall();
        Sanctum::actingAs($caller);

        $this->postJson($this->url($call), ['type' => 'video_state', 'payload' => ['enabled' => 'banana']])
            ->assertStatus(422);
    }

    public function test_oversize_payload_is_rejected(): void
    {
        [$call, $caller] = $this->makeCall();
        Sanctum::actingAs($caller);

        $this->postJson($this->url($call), ['type' => 'offer', 'payload' => ['sdp' => str_repeat('a', 70000)]])
            ->assertStatus(422);
    }

    public function test_active_call_since_seq_returns_only_newer_signals(): void
    {
        [$call, $caller, $receiver] = $this->makeCall();
        Sanctum::actingAs($caller);
        $this->postJson($this->url($call), ['type' => 'candidate', 'payload' => ['candidate' => 'a']])->assertOk();
        $this->postJson($this->url($call), ['type' => 'candidate', 'payload' => ['candidate' => 'b']])->assertOk();

        Sanctum::actingAs($receiver);
        $all = $this->getJson("/api/v1.0/chat/calls/active?call_id={$call->id}")->assertOk()->json('signals');
        $newer = $this->getJson("/api/v1.0/chat/calls/active?call_id={$call->id}&since_seq=1")->assertOk()->json('signals');

        $this->assertCount(2, $all);
        $this->assertCount(1, $newer);
        $this->assertSame(2, $newer[0]['seq']);
    }

    public function test_missed_reason_marks_call_missed(): void
    {
        [$call, , $receiver] = $this->makeCall('video', 'ringing');
        Sanctum::actingAs($receiver);

        $this->postJson("/api/v1.0/chat/calls/{$call->id}/reject", ['reason' => 'missed'])->assertOk();
        $this->assertSame('missed', $call->fresh()->status);
    }
}
