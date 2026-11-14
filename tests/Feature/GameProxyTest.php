<?php

namespace Tests\Feature;

use App\Models\AuthAccount;
use App\Models\Game;
use App\Models\GameSave;
use App\Services\GameProxyToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GameProxyTest extends TestCase
{
    use RefreshDatabase;

    private function game(bool $useProxy = true): Game
    {
        return Game::create([
            'name' => 'Test game',
            'slug' => 'test-game',
            'category' => 'arcade',
            'image_url' => 'https://example.com/i.png',
            'iframe_url' => 'https://games.example.com/play/index.html',
            'platform' => 'both',
            'use_proxy' => $useProxy,
            'is_active' => true,
        ]);
    }

    private function enable(): void
    {
        config([
            'gameproxy.enabled' => true,
            'gameproxy.secret' => 'test-secret',
            'gameproxy.public_url' => 'https://play.example.net',
        ]);
    }

    public function test_link_is_a_signed_proxy_url_when_the_game_is_proxied(): void
    {
        $this->enable();
        $this->game();
        $user = AuthAccount::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1.0/games/test-game/proxy-link')->assertOk();

        $this->assertTrue($response->json('enabled'));
        $url = $response->json('url');
        $this->assertStringStartsWith('https://play.example.net/play?t=', $url);

        $payload = GameProxyToken::verify(substr($url, strlen('https://play.example.net/play?t=')));
        $this->assertSame($user->id, $payload['u']);
        $this->assertSame('https://games.example.com/play/index.html', $payload['o']);
    }

    public function test_the_link_carries_the_games_proxy_options_from_the_database(): void
    {
        $this->enable();
        $game = $this->game();
        $game->update(['proxy_options' => ['block_hosts' => ['ads.example.com'], 'allow_hosts' => ['cdn.example.com']]]);
        Sanctum::actingAs(AuthAccount::factory()->create());

        $url = $this->postJson('/api/v1.0/games/test-game/proxy-link')->json('url');
        $payload = GameProxyToken::verify(substr($url, strlen('https://play.example.net/play?t=')));

        $this->assertSame(['ads.example.com'], $payload['x']['b']);
        $this->assertSame(['cdn.example.com'], $payload['x']['a']);
    }

    public function test_link_says_disabled_when_the_proxy_is_off_or_the_game_is_not_proxied(): void
    {
        $this->game(false);
        Sanctum::actingAs(AuthAccount::factory()->create());

        $this->postJson('/api/v1.0/games/test-game/proxy-link')->assertOk()->assertJson(['enabled' => false]);
    }

    public function test_internal_endpoints_need_the_shared_key(): void
    {
        $this->enable();
        $this->game();
        $user = AuthAccount::factory()->create();

        $this->getJson("/api/v1.0/internal/game-proxy/saves/{$user->id}/test-game")->assertNotFound();
        $this->getJson("/api/v1.0/internal/game-proxy/saves/{$user->id}/test-game", ['X-Game-Proxy-Key' => 'wrong'])
            ->assertNotFound();
    }

    public function test_the_proxy_saves_and_loads_cookies_and_storage(): void
    {
        $this->enable();
        $this->game();
        $user = AuthAccount::factory()->create();
        $key = ['X-Game-Proxy-Key' => 'test-secret'];

        $this->putJson("/api/v1.0/internal/game-proxy/saves/{$user->id}/test-game", [
            'cookies' => [['name' => 'lvl', 'value' => '7']],
            'storage' => ['coins' => '120', 'settings' => '{"sound":true}'],
        ], $key)->assertOk();

        $this->getJson("/api/v1.0/internal/game-proxy/saves/{$user->id}/test-game", $key)
            ->assertOk()
            ->assertJsonPath('cookies.0.name', 'lvl')
            ->assertJsonPath('storage.coins', '120');

        $this->assertSame(1, GameSave::count());
    }

    public function test_a_save_over_the_size_limit_is_refused(): void
    {
        $this->enable();
        config(['gameproxy.max_save_bytes' => 100]);
        $this->game();
        $user = AuthAccount::factory()->create();

        $this->putJson("/api/v1.0/internal/game-proxy/saves/{$user->id}/test-game", [
            'storage' => ['big' => str_repeat('x', 500)],
        ], ['X-Game-Proxy-Key' => 'test-secret'])->assertStatus(413);
    }

    public function test_the_account_can_see_and_delete_its_save(): void
    {
        $this->enable();
        $game = $this->game();
        $user = AuthAccount::factory()->create();
        GameSave::create(['user_id' => $user->id, 'game_id' => $game->id, 'cookies' => '[]', 'storage' => '{}', 'size_bytes' => 4]);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1.0/games/test-game/save')->assertOk()->assertJson(['has_save' => true]);
        $this->deleteJson('/api/v1.0/games/test-game/save')->assertOk();
        $this->getJson('/api/v1.0/games/test-game/save')->assertJson(['has_save' => false]);
    }
}
