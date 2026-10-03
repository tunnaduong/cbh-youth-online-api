<?php

namespace Tests\Unit;

use App\Services\TotpService;
use PHPUnit\Framework\TestCase;

class TotpServiceTest extends TestCase
{
  // RFC 6238 appendix B test secret ("12345678901234567890"), base32 encoded.
  private const SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

  public function test_base32_round_trip(): void
  {
    $this->assertSame(self::SECRET, TotpService::base32Encode('12345678901234567890'));
    $this->assertSame('12345678901234567890', TotpService::base32Decode(self::SECRET));
    // Authenticator apps show secrets in lowercase groups; both must decode.
    $this->assertSame('12345678901234567890', TotpService::base32Decode('gezd gnbv gy3t qojq gezd gnbv gy3t qojq'));
  }

  public function test_generated_secret_is_160_bits_of_base32(): void
  {
    $secret = TotpService::generateSecret();

    $this->assertMatchesRegularExpression('/^[A-Z2-7]{32}$/', $secret);
    $this->assertSame(20, strlen(TotpService::base32Decode($secret)));
    $this->assertNotSame($secret, TotpService::generateSecret());
  }

  /**
   * The SHA-1 vectors from RFC 6238 appendix B, truncated to 6 digits.
   */
  public function test_codes_match_rfc_6238_vectors(): void
  {
    $vectors = [
      59 => '287082',
      1111111109 => '081804',
      1111111111 => '050471',
      1234567890 => '005924',
      2000000000 => '279037',
      20000000000 => '353130',
    ];

    foreach ($vectors as $timestamp => $code) {
      $this->assertSame($code, TotpService::codeAt(self::SECRET, intdiv($timestamp, TotpService::PERIOD)));
    }
  }

  public function test_verify_accepts_current_and_adjacent_steps_only(): void
  {
    $now = 1234567890;
    $step = intdiv($now, TotpService::PERIOD);

    $this->assertSame($step, TotpService::verify(self::SECRET, '005924', null, 1, $now));
    $this->assertSame($step, TotpService::verify(self::SECRET, '005 924', null, 1, $now));
    $this->assertSame($step - 1, TotpService::verify(self::SECRET, TotpService::codeAt(self::SECRET, $step - 1), null, 1, $now));
    $this->assertSame($step + 1, TotpService::verify(self::SECRET, TotpService::codeAt(self::SECRET, $step + 1), null, 1, $now));
    $this->assertNull(TotpService::verify(self::SECRET, TotpService::codeAt(self::SECRET, $step - 2), null, 1, $now));
    $this->assertNull(TotpService::verify(self::SECRET, TotpService::codeAt(self::SECRET, $step + 2), null, 1, $now));
  }

  public function test_verify_rejects_malformed_codes_and_empty_secret(): void
  {
    $now = 1234567890;

    $this->assertNull(TotpService::verify(self::SECRET, '', null, 1, $now));
    $this->assertNull(TotpService::verify(self::SECRET, '05924', null, 1, $now));
    $this->assertNull(TotpService::verify(self::SECRET, 'abcdef', null, 1, $now));
    $this->assertNull(TotpService::verify('', '005924', null, 1, $now));
  }

  public function test_verify_rejects_a_replayed_code(): void
  {
    $now = 1234567890;
    $step = intdiv($now, TotpService::PERIOD);

    $this->assertSame($step, TotpService::verify(self::SECRET, '005924', $step - 1, 1, $now));
    $this->assertNull(TotpService::verify(self::SECRET, '005924', $step, 1, $now));
  }

  public function test_provisioning_uri(): void
  {
    $this->assertSame(
      'otpauth://totp/CBH%20Youth%20Online:user%40example.com?secret=' . self::SECRET
        . '&issuer=CBH%20Youth%20Online&algorithm=SHA1&digits=6&period=30',
      TotpService::provisioningUri(self::SECRET, 'user@example.com', 'CBH Youth Online')
    );
  }
}
