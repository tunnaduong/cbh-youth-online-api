<?php

namespace Tests\Feature;

use App\Models\AuthAccount;
use App\Models\Game;
use App\Models\GamePlayerState;
use App\Services\GameProxyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GameProxyTest extends TestCase
{
    use RefreshDatabase;

    private const IFRAME = 'https://games.cdn.famobi.com/html5games/o/om-nom-run/v1240/?fg_domain=play.famobi.com&fg_uid=x';

    private function game(): Game
    {
        return Game::create([
            'name' => 'Om Nom Run',
            'slug' => 'om-nom-run',
            'category' => 'arcade',
            'image_url' => 'https://example.com/a.jpg',
            'iframe_url' => self::IFRAME,
            'platform' => 'both',
            'is_active' => true,
        ]);
    }

    private function ticketPath(): string
    {
        $this->game();
        $user = AuthAccount::create([
            'username' => 'player_'.uniqid(),
            'email' => uniqid('p').'@example.test',
            'password' => bcrypt('secret-pass'),
            'role' => 'user',
            'points' => 0,
        ]);
        Sanctum::actingAs($user);

        $url = $this->postJson('/v1.0/games/om-nom-run/play-ticket')->assertOk()->json('url');

        return parse_url($url, PHP_URL_PATH);
    }

    public function test_ticket_endpoint_requires_login(): void
    {
        $this->game();
        $this->postJson('/v1.0/games/om-nom-run/play-ticket')->assertUnauthorized();
    }

    public function test_page_is_rewritten_sandboxed_and_sets_cookies_on_the_server(): void
    {
        $html = '<html><head><script>(function (document, url, fgJS, firstJS) {'
          ."})(document, '/html5games/gameapi/v1.js?e=');</script></head><body>game</body></html>";

        Http::fake([
            'games.cdn.famobi.com/*' => Http::response($html, 200, [
                'Content-Type' => 'text/html',
                'Set-Cookie' => 'uid=abc123; Path=/; Secure',
            ]),
        ]);

        $path = $this->ticketPath();
        $this->assertStringEndsWith('/', $path);
        $response = $this->get($path);

        $response->assertOk();
        $response->assertHeader('Content-Security-Policy', GameProxyService::SANDBOX_CSP);
        $response->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertStringNotContainsString('gameapi/v1.js', $response->getContent());
        $this->assertStringContainsString('<base href=', $response->getContent());

        // The cookie stayed on the server, not in the browser.
        $response->assertCookieMissing('uid');
        $this->assertSame(1, GamePlayerState::count());
    }

    public function test_stored_cookies_are_replayed_on_the_next_file_request(): void
    {
        Http::fake([
            'games.cdn.famobi.com/*/styles.css' => Http::response('body{}', 200, ['Content-Type' => 'text/css']),
            'games.cdn.famobi.com/*' => Http::response('<html><head></head></html>', 200, [
                'Content-Type' => 'text/html',
                'Set-Cookie' => 'progress=level7; Path=/; Secure',
            ]),
        ]);

        $path = $this->ticketPath();
        $this->get($path)->assertOk();

        $css = $this->get($path.'styles.css')->assertOk();
        $this->assertStringStartsWith('text/css', $css->headers->get('Content-Type'));

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/styles.css')
          && str_contains($request->header('Cookie')[0] ?? '', 'progress=level7'));
    }

    public function test_a_file_outside_the_game_folder_is_not_fetched(): void
    {
        Http::fake();
        $path = $this->ticketPath();

        $this->get($path.'..%2F..%2Fsecret.js')->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_an_invalid_ticket_is_refused(): void
    {
        Http::fake();
        $this->get('/v1.0/games/play/1.1.9999999999.'.str_repeat('a', 64).'/')->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_saved_local_storage_is_handed_back_to_the_page_on_any_device(): void
    {
        Http::fake([
            'games.cdn.famobi.com/*' => Http::response('<html><head></head><body></body></html>', 200, ['Content-Type' => 'text/html']),
        ]);

        $path = $this->ticketPath();

        // The game saves its progress from one device (the shim posts the whole map).
        $this->call('POST', $path.'_save', [], [], [], ['CONTENT_TYPE' => 'text/plain'], json_encode([
            'level' => '7',
            'coins' => '1200',
        ]))->assertNoContent();

        // Opening the game later, anywhere, gets the saved map back in the page.
        $html = $this->get($path)->assertOk()->getContent();
        $this->assertStringContainsString('window.__CYO_GAME_STORAGE__ = {"level":"7","coins":"1200"};', $html);
    }

    public function test_storage_with_non_string_values_is_refused_and_nothing_is_saved(): void
    {
        Http::fake();
        $path = $this->ticketPath();

        $this->call('POST', $path.'_save', [], [], [], ['CONTENT_TYPE' => 'text/plain'], json_encode(['score' => 12]))
            ->assertStatus(422);

        $this->assertSame(0, GamePlayerState::count());
    }

    public function test_a_saved_value_with_script_or_dollar_text_does_not_break_the_page(): void
    {
        Http::fake([
            'games.cdn.famobi.com/*' => Http::response('<html><head></head><body></body></html>', 200, ['Content-Type' => 'text/html']),
        ]);
        $path = $this->ticketPath();

        $this->call('POST', $path.'_save', [], [], [], ['CONTENT_TYPE' => 'text/plain'], json_encode([
            'name' => '</script><b>$1 \\ x',
        ]))->assertNoContent();

        $html = $this->get($path)->assertOk()->getContent();
        $this->assertStringNotContainsString('</script><b>', $html);
        $this->assertStringContainsString('\\u003C/script', $html);
        $this->assertStringContainsString('<base href=', $html);
    }
}
