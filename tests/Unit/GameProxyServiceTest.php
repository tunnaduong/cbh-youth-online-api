<?php

namespace Tests\Unit;

use App\Models\Game;
use App\Services\GameProxyService;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class GameProxyServiceTest extends TestCase
{
    private GameProxyService $proxy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->proxy = new GameProxyService;
    }

    private function game(string $iframe): Game
    {
        return new Game(['slug' => 'g', 'iframe_url' => $iframe]);
    }

    public function test_ticket_round_trips_and_rejects_tampering(): void
    {
        [$token] = $this->proxy->issueTicket(42, 7);

        $this->assertSame(['user_id' => 42, 'game_id' => 7], $this->proxy->verifyTicket($token));

        $tampered = preg_replace('/^42\./', '43.', $token);
        $this->assertNull($this->proxy->verifyTicket($tampered));
        $this->assertNull($this->proxy->verifyTicket('garbage'));
    }

    public function test_ticket_expires(): void
    {
        Carbon::setTestNow(now());
        [$token] = $this->proxy->issueTicket(1, 1);
        Carbon::setTestNow(now()->addSeconds(GameProxyService::TICKET_TTL_SECONDS + 1));

        $this->assertNull($this->proxy->verifyTicket($token));
        Carbon::setTestNow();
    }

    public function test_base_folder_comes_from_the_iframe_address(): void
    {
        $this->assertSame(
            'https://games.cdn.famobi.com/html5games/o/om-nom-run/v1240/',
            $this->proxy->gameBase($this->game('https://games.cdn.famobi.com/html5games/o/om-nom-run/v1240/?fg_domain=play.famobi.com&fg_uid=x'))
        );
        // A bare folder without a trailing slash is still a folder.
        $this->assertSame('https://dhp2010.is-a.dev/minecraft/', $this->proxy->gameBase($this->game('https://dhp2010.is-a.dev/minecraft')));
        // A file address keeps its folder.
        $this->assertSame('https://example.com/a/b/', $this->proxy->gameBase($this->game('https://example.com/a/b/index.html')));
        // Only https is served.
        $this->assertNull($this->proxy->gameBase($this->game('http://example.com/game/')));
    }

    public function test_asset_paths_cannot_leave_the_game_folder(): void
    {
        $game = $this->game('https://games.cdn.famobi.com/html5games/o/om-nom-run/v1240/');

        $this->assertSame(
            'https://games.cdn.famobi.com/html5games/o/om-nom-run/v1240/img/logo.png',
            $this->proxy->assetUrl($game, 'img/logo.png')
        );
        $this->assertSame(
            'https://games.cdn.famobi.com/html5games/o/om-nom-run/v1240/x.js?v=2',
            $this->proxy->assetUrl($game, 'x.js', 'v=2')
        );

        foreach (['../secret.js', 'a/../../b', '%2e%2e/secret.js', '%2E%2E%2Fsecret.js', 'a%5C..%5Cb', "a\0b"] as $bad) {
            $this->assertNull($this->proxy->assetUrl($game, $bad), $bad);
        }
    }

    public function test_cookie_header_matches_domain_path_and_expiry(): void
    {
        $jar = [
            ['name' => 'a', 'value' => '1', 'domain' => 'games.cdn.famobi.com', 'path' => '/', 'expires' => null, 'secure' => true],
            ['name' => 'b', 'value' => '2', 'domain' => 'famobi.com', 'path' => '/html5games/', 'expires' => null, 'secure' => true],
            ['name' => 'c', 'value' => '3', 'domain' => 'games.cdn.famobi.com', 'path' => '/', 'expires' => 100, 'secure' => true],
            ['name' => 'd', 'value' => '4', 'domain' => 'other.test', 'path' => '/', 'expires' => null, 'secure' => true],
        ];

        $this->assertSame(
            'a=1; b=2',
            $this->proxy->cookieHeader($jar, 'games.cdn.famobi.com', '/html5games/o/x/', 200)
        );
        $this->assertSame('a=1', $this->proxy->cookieHeader($jar, 'games.cdn.famobi.com', '/other/', 200));
    }

    public function test_document_drops_the_famobi_bootstrap_and_keeps_the_game_script_list(): void
    {
        $html = <<<'HTML'
<html><head>
<script>
  window.famobi_gameJS = [ "preloader.js", "__start__.js" ];
</script>
<script>
  (function (document, url, fgJS, firstJS) {
    fgJS = document.createElement('script');
    firstJS.parentNode.insertBefore(fgJS, firstJS);
  })(document, '/html5games/gameapi/v1.js?e=');
</script>
<script src="https://securepubads.g.doubleclick.net/tag/js/gpt.js"></script>
<script src="__start__.js"></script>
</head><body></body></html>
HTML;

        $game = $this->game('https://games.cdn.famobi.com/html5games/o/om-nom-run/v1240/?fg_domain=x');
        $out = $this->proxy->rewriteDocument($html, 'https://api.example.test/v1.0/games/play/TOKEN/', $game);

        $this->assertStringNotContainsString('gameapi/v1.js', $out);
        $this->assertStringNotContainsString('doubleclick', $out);
        $this->assertStringContainsString('window.famobi_gameJS = [ "preloader.js", "__start__.js" ];', $out);
        $this->assertStringContainsString('<base href="https://api.example.test/v1.0/games/play/TOKEN/">', $out);
        $this->assertStringContainsString('window.famobi = new Proxy(api', $out);
        // The game's own script is still there, and loads relative to the proxy base.
        $this->assertStringContainsString('<script src="__start__.js"></script>', $out);
    }
}
