<?php

namespace Tests\Unit;

use App\Services\GameProxyToken;
use Tests\TestCase;

class GameProxyTokenTest extends TestCase
{
  protected function setUp(): void
  {
    parent::setUp();
    config(['gameproxy.secret' => 'test-secret', 'gameproxy.link_ttl' => 3600]);
  }

  public function test_a_token_round_trips(): void
  {
    $token = GameProxyToken::make(42, 'om-nom-run', 'https://games.example.com/x.html');
    $payload = GameProxyToken::verify($token);

    $this->assertSame(42, $payload['u']);
    $this->assertSame('om-nom-run', $payload['g']);
    $this->assertSame('https://games.example.com/x.html', $payload['o']);
  }

  public function test_a_forged_or_expired_token_is_rejected(): void
  {
    $token = GameProxyToken::make(1, 'g', 'https://a.example/', 3600);
    [$body] = explode('.', $token);

    $this->assertNull(GameProxyToken::verify($body . '.AAAA'));
    $this->assertNull(GameProxyToken::verify('garbage'));
    $this->assertNull(GameProxyToken::verify(GameProxyToken::make(1, 'g', 'https://a.example/', -10)));

    config(['gameproxy.secret' => 'another-secret']);
    $this->assertNull(GameProxyToken::verify($token));
  }

  /** The Go server (game-proxy/token_test.go) checks this same vector. */
  public function test_matches_the_vector_the_go_server_verifies(): void
  {
    $payload = json_encode([
      'u' => 42, 'g' => 'om-nom-run',
      'o' => 'https://games.example.com/om-nom/index.html?lang=vi',
      'e' => 4102444800,
    ], JSON_UNESCAPED_SLASHES);
    $body = rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
    $sig = rtrim(strtr(base64_encode(hash_hmac('sha256', $body, 'test-secret', true)), '+/', '-_'), '=');

    $this->assertSame(
      'zi0am6958WrY8ubEXqlZiaAkYYEjvPM64Hjjf6jCZ5c',
      $sig
    );
    $this->assertNotNull(GameProxyToken::verify($body . '.' . $sig));
  }
}
