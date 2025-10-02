<?php

namespace Tests\Feature;

use App\Models\AuthAccount;
use App\Models\Topic;
use App\Models\TopicVote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class VotingTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $topic;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test user
        $this->user = AuthAccount::factory()->create([
            'username' => 'testuser',
            'email' => 'test@example.com',
        ]);

        // Create a test topic
        $this->topic = Topic::factory()->create([
            'title' => 'Test Topic',
            'content_html' => 'Test content',
            'user_id' => $this->user->id,
        ]);
    }

    public function test_user_can_upvote_topic()
    {
        $this->actingAs($this->user);

        $response = $this->postJson("/api/v1.0/topics/{$this->topic->id}/votes", [
            'vote_value' => 1
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'message' => 'Vote registered',
                    'vote_value' => 1,
                    'total_votes' => 1,
                ]);

        $this->assertDatabaseHas('cyo_topic_votes', [
            'topic_id' => $this->topic->id,
            'user_id' => $this->user->id,
            'vote_value' => 1,
        ]);
    }

    public function test_user_can_downvote_topic()
    {
        $this->actingAs($this->user);

        $response = $this->postJson("/api/v1.0/topics/{$this->topic->id}/votes", [
            'vote_value' => -1
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'message' => 'Vote registered',
                    'vote_value' => -1,
                    'total_votes' => -1,
                ]);

        $this->assertDatabaseHas('cyo_topic_votes', [
            'topic_id' => $this->topic->id,
            'user_id' => $this->user->id,
            'vote_value' => -1,
        ]);
    }

    public function test_user_can_change_vote_from_upvote_to_downvote()
    {
        $this->actingAs($this->user);

        // First upvote
        $this->postJson("/api/v1.0/topics/{$this->topic->id}/votes", [
            'vote_value' => 1
        ]);

        // Then downvote
        $response = $this->postJson("/api/v1.0/topics/{$this->topic->id}/votes", [
            'vote_value' => -1
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'message' => 'Vote registered',
                    'vote_value' => -1,
                    'total_votes' => -1,
                ]);

        $this->assertDatabaseHas('cyo_topic_votes', [
            'topic_id' => $this->topic->id,
            'user_id' => $this->user->id,
            'vote_value' => -1,
        ]);
    }

    public function test_user_can_remove_vote()
    {
        $this->actingAs($this->user);

        // First upvote
        $this->postJson("/api/v1.0/topics/{$this->topic->id}/votes", [
            'vote_value' => 1
        ]);

        // Then remove vote
        $response = $this->postJson("/api/v1.0/topics/{$this->topic->id}/votes", [
            'vote_value' => 0
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'message' => 'Vote registered',
                    'vote_value' => 0,
                    'total_votes' => 0,
                ]);

        $this->assertDatabaseMissing('cyo_topic_votes', [
            'topic_id' => $this->topic->id,
            'user_id' => $this->user->id,
        ]);
    }

    public function test_user_cannot_vote_multiple_times_with_same_value()
    {
        $this->actingAs($this->user);

        // First upvote
        $this->postJson("/api/v1.0/topics/{$this->topic->id}/votes", [
            'vote_value' => 1
        ]);

        // Try to upvote again
        $response = $this->postJson("/api/v1.0/topics/{$this->topic->id}/votes", [
            'vote_value' => 1
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'total_votes' => 1,
                ]);

        // Should still have only one vote record
        $this->assertEquals(1, TopicVote::where('topic_id', $this->topic->id)->count());
    }

    public function test_multiple_users_can_vote_on_same_topic()
    {
        $user2 = AuthAccount::factory()->create([
            'username' => 'testuser2',
            'email' => 'test2@example.com',
        ]);

        // User 1 upvotes
        $this->actingAs($this->user);
        $this->postJson("/api/v1.0/topics/{$this->topic->id}/votes", [
            'vote_value' => 1
        ]);

        // User 2 downvotes
        $this->actingAs($user2);
        $response = $this->postJson("/api/v1.0/topics/{$this->topic->id}/votes", [
            'vote_value' => -1
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'total_votes' => 0, // 1 + (-1) = 0
                ]);

        $this->assertEquals(2, TopicVote::where('topic_id', $this->topic->id)->count());
    }

    public function test_unauthenticated_user_cannot_vote()
    {
        $response = $this->postJson("/api/v1.0/topics/{$this->topic->id}/votes", [
            'vote_value' => 1
        ]);

        $response->assertStatus(401);
    }

    public function test_invalid_vote_value_is_rejected()
    {
        $this->actingAs($this->user);

        $response = $this->postJson("/api/v1.0/topics/{$this->topic->id}/votes", [
            'vote_value' => 2 // Invalid value
        ]);

        $response->assertStatus(422);
    }
}
