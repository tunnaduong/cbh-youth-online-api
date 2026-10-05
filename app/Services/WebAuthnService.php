<?php

namespace App\Services;

use App\Models\AuthAccount;
use App\Models\Passkey;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Passkeys (WebAuthn) as a way to log in without a password, without a
 * package: only what this app needs - "none" attestation at registration and
 * ES256 / RS256 signatures at login. A passkey login is complete on its own:
 * the passkey lives on the user's device behind its screen lock, so no
 * two-factor step follows.
 *
 * The browser talks to the authenticator; this class creates the challenges,
 * checks what comes back (challenge, origin, relying-party id, user-present
 * flag, signature) and stores each credential's public key as PEM.
 */
class WebAuthnService
{
  private const CHALLENGE_TTL = 300;

  // COSE algorithm ids offered to the authenticator.
  private const ALG_ES256 = -7;
  private const ALG_RS256 = -257;

  // DER header of a P-256 SubjectPublicKeyInfo, up to the uncompressed point.
  private const P256_SPKI_PREFIX = '3059301306072a8648ce3d020106082a8648ce3d030107034200';

  /**
   * The domain passkeys are bound to. A passkey made for one relying-party
   * id can't be used on another, so this must not change once users have
   * registered.
   */
  public static function rpId(): string
  {
    return (string) config('services.webauthn.rp_id', 'www.chuyenbienhoa.com');
  }

  /**
   * Relying-party ids a passkey may have been made for: the current one and
   * the ones used before it (services.webauthn.rp_ids), so a passkey made
   * for the bare domain still verifies where a browser can still offer it.
   */
  public static function acceptedRpIds(): array
  {
    $ids = array_map('trim', explode(',', (string) config('services.webauthn.rp_ids', 'chuyenbienhoa.com')));

    return array_values(array_unique(array_filter([self::rpId(), ...$ids])));
  }

  /**
   * Origins allowed to run the ceremony: the web site (which is also what
   * the iOS app reports) and the Android app, whose origin is the hash of
   * its signing certificate (services.webauthn.android_origins).
   */
  public static function origins(): array
  {
    $origins = config('services.webauthn.origins', 'https://chuyenbienhoa.com,https://www.chuyenbienhoa.com')
      . ',' . config('services.webauthn.android_origins', '');

    return array_values(array_filter(array_map('trim', explode(',', $origins))));
  }

  /**
   * Options for navigator.credentials.create().
   */
  public static function registrationOptions(AuthAccount $user): array
  {
    $challenge = random_bytes(32);
    Cache::put(self::registrationKey($user), self::b64urlEncode($challenge), self::CHALLENGE_TTL);

    return [
      'rp' => ['id' => self::rpId(), 'name' => TwoFactorService::ISSUER],
      'user' => [
        'id' => self::b64urlEncode('cyo-' . $user->id),
        'name' => $user->username,
        'displayName' => $user->profile->profile_name ?? $user->username,
      ],
      'challenge' => self::b64urlEncode($challenge),
      'pubKeyCredParams' => [
        ['type' => 'public-key', 'alg' => self::ALG_ES256],
        ['type' => 'public-key', 'alg' => self::ALG_RS256],
      ],
      'timeout' => self::CHALLENGE_TTL * 1000,
      'attestation' => 'none',
      'authenticatorSelection' => [
        // Stored on the device, so login needs no username.
        'residentKey' => 'required',
        'requireResidentKey' => true,
        'userVerification' => 'required',
      ],
      // Stops the same authenticator being registered twice.
      'excludeCredentials' => self::credentialDescriptors($user),
    ];
  }

  /**
   * Check the result of navigator.credentials.create() and store the new
   * credential. Returns null when anything doesn't add up.
   *
   * @param  array  $credential  { id, response: { clientDataJSON, attestationObject } } (base64url)
   */
  public static function register(AuthAccount $user, array $credential, ?string $name = null): ?Passkey
  {
    $expected = Cache::pull(self::registrationKey($user));
    $clientDataJson = self::b64urlDecode((string) data_get($credential, 'response.clientDataJSON'));
    $attestation = self::b64urlDecode((string) data_get($credential, 'response.attestationObject'));

    if (!$expected || !self::clientDataMatches($clientDataJson, 'webauthn.create', $expected)) {
      return null;
    }

    try {
      $offset = 0;
      $object = self::cborDecode($attestation, $offset);
      $authData = is_array($object) ? ($object['authData'] ?? null) : null;
      if (!is_string($authData) || strlen($authData) < 55) {
        return null;
      }

      $parsed = self::parseAuthenticatorData($authData);
      // 0x40 = attested credential data is present; 0x04 = the user was
      // verified (fingerprint, face or PIN).
      if (!$parsed || !($parsed['flags'] & 0x40) || !($parsed['flags'] & 0x04)) {
        return null;
      }

      $idLength = unpack('n', substr($authData, 53, 2))[1];
      $credentialId = substr($authData, 55, $idLength);
      if ($idLength < 1 || strlen($credentialId) !== $idLength) {
        return null;
      }

      $keyOffset = 55 + $idLength;
      $pem = self::coseKeyToPem(self::cborDecode($authData, $keyOffset));
    } catch (\Throwable $e) {
      return null;
    }

    if (!$pem) {
      return null;
    }

    $encodedId = self::b64urlEncode($credentialId);
    $hash = hash('sha256', $encodedId);

    // A credential belongs to exactly one account.
    if (Passkey::where('credential_hash', $hash)->exists()) {
      return null;
    }

    return Passkey::create([
      'user_id' => $user->id,
      'credential_id' => $encodedId,
      'credential_hash' => $hash,
      'public_key' => $pem,
      'sign_count' => $parsed['sign_count'],
      'name' => $name ? Str::limit($name, 250, '') : null,
    ]);
  }

  /**
   * Start a passkey login: options for navigator.credentials.get(). Nobody
   * is identified yet, so the challenge is kept under a random request id
   * the client sends back with the result.
   */
  public static function loginOptions(): array
  {
    $challenge = random_bytes(32);
    $requestId = Str::random(40);
    Cache::put(self::loginKey($requestId), self::b64urlEncode($challenge), self::CHALLENGE_TTL);

    return [
      'request_id' => $requestId,
      'publicKey' => [
        'challenge' => self::b64urlEncode($challenge),
        'rpId' => self::rpId(),
        'timeout' => self::CHALLENGE_TTL * 1000,
        'userVerification' => 'required',
        // Empty: the device offers whichever passkeys it holds for this site.
        'allowCredentials' => [],
      ],
    ];
  }

  /**
   * Check the result of navigator.credentials.get() and return the account
   * the passkey belongs to, or null when anything doesn't add up.
   *
   * @param  array  $credential  { id, response: { clientDataJSON, authenticatorData, signature } } (base64url)
   */
  public static function verifyLogin(array $credential, string $requestId): ?AuthAccount
  {
    $expected = Cache::pull(self::loginKey($requestId));
    $clientDataJson = self::b64urlDecode((string) data_get($credential, 'response.clientDataJSON'));
    $authData = self::b64urlDecode((string) data_get($credential, 'response.authenticatorData'));
    $signature = self::b64urlDecode((string) data_get($credential, 'response.signature'));

    if (!$expected || !self::clientDataMatches($clientDataJson, 'webauthn.get', $expected)) {
      return null;
    }

    // Re-encode so base64 padding or "+/" variants of the id still match.
    $encodedId = self::b64urlEncode(self::b64urlDecode((string) data_get($credential, 'id')));
    $passkey = Passkey::where('credential_hash', hash('sha256', $encodedId))->first();

    $parsed = self::parseAuthenticatorData($authData);
    if (!$passkey || !$parsed || $signature === '') {
      return null;
    }

    // A passkey login replaces the password AND two-factor, which is only
    // sound if the device verified its user (0x04: fingerprint, face or PIN)
    // and not merely that someone touched it.
    if (!($parsed['flags'] & 0x04)) {
      return null;
    }

    // The authenticator signs its data followed by the hash of the client data.
    $signed = $authData . hash('sha256', $clientDataJson, true);
    if (openssl_verify($signed, $signature, $passkey->public_key, OPENSSL_ALGO_SHA256) !== 1) {
      return null;
    }

    // A counter that doesn't move forward means the credential was cloned.
    // Synced passkeys always report 0 (and so have 0 stored), which is fine.
    if (($parsed['sign_count'] !== 0 || $passkey->sign_count > 0) && $parsed['sign_count'] <= $passkey->sign_count) {
      return null;
    }

    $passkey->forceFill([
      'sign_count' => $parsed['sign_count'],
      'last_used_at' => now(),
    ])->save();

    return AuthAccount::find($passkey->user_id);
  }

  private static function credentialDescriptors(AuthAccount $user): array
  {
    return Passkey::where('user_id', $user->id)
      ->pluck('credential_id')
      ->map(fn($id) => ['type' => 'public-key', 'id' => $id])
      ->all();
  }

  /**
   * The client data must be for the expected ceremony and challenge, and
   * come from one of our origins.
   */
  private static function clientDataMatches(string $json, string $type, string $challenge): bool
  {
    $data = json_decode($json, true);

    return is_array($data)
      && ($data['type'] ?? null) === $type
      && is_string($data['challenge'] ?? null)
      && hash_equals($challenge, rtrim($data['challenge'], '='))
      && in_array($data['origin'] ?? null, self::origins(), true);
  }

  /**
   * Fixed part of the authenticator data: 32-byte hash of the relying-party
   * id, one flags byte, 4-byte signature counter. Null unless it is for our
   * relying party and the user was present (flag 0x01).
   */
  private static function parseAuthenticatorData(string $authData): ?array
  {
    if (strlen($authData) < 37) {
      return null;
    }

    $flags = ord($authData[32]);

    // For the relying party passkeys are made for now, or one they were made
    // for before (the bare domain and www are the same site).
    $rpIdHash = substr($authData, 0, 32);
    $ours = false;
    foreach (self::acceptedRpIds() as $rpId) {
      $ours = $ours || hash_equals(hash('sha256', $rpId, true), $rpIdHash);
    }

    if (!$ours || !($flags & 0x01)) {
      return null;
    }

    return [
      'flags' => $flags,
      'sign_count' => unpack('N', substr($authData, 33, 4))[1],
    ];
  }

  /**
   * COSE public key (as decoded CBOR map) -> PEM, for the two algorithms
   * offered at registration. Null for anything else.
   */
  private static function coseKeyToPem($key): ?string
  {
    if (!is_array($key)) {
      return null;
    }

    $type = $key[1] ?? null;
    $alg = $key[3] ?? null;

    // EC2 key on P-256: -1 = curve (1 = P-256), -2 = x, -3 = y.
    if ($type === 2 && $alg === self::ALG_ES256 && ($key[-1] ?? null) === 1) {
      $x = $key[-2] ?? '';
      $y = $key[-3] ?? '';
      if (!is_string($x) || !is_string($y) || strlen($x) !== 32 || strlen($y) !== 32) {
        return null;
      }

      return self::pem(hex2bin(self::P256_SPKI_PREFIX) . "\x04" . $x . $y);
    }

    // RSA key: -1 = modulus, -2 = exponent.
    if ($type === 3 && $alg === self::ALG_RS256) {
      $n = $key[-1] ?? '';
      $e = $key[-2] ?? '';
      if (!is_string($n) || !is_string($e) || strlen($n) < 256 || $e === '') {
        return null;
      }

      $rsaKey = self::der(0x30, self::derInteger($n) . self::derInteger($e));
      // rsaEncryption OID with NULL parameters.
      $algorithm = hex2bin('300d06092a864886f70d0101010500');

      return self::pem(self::der(0x30, $algorithm . self::der(0x03, "\x00" . $rsaKey)));
    }

    return null;
  }

  private static function pem(string $der): string
  {
    return "-----BEGIN PUBLIC KEY-----\n"
      . chunk_split(base64_encode($der), 64, "\n")
      . "-----END PUBLIC KEY-----\n";
  }

  private static function der(int $tag, string $content): string
  {
    $length = strlen($content);

    if ($length < 0x80) {
      $encoded = chr($length);
    } else {
      $bytes = ltrim(pack('N', $length), "\x00");
      $encoded = chr(0x80 | strlen($bytes)) . $bytes;
    }

    return chr($tag) . $encoded . $content;
  }

  private static function derInteger(string $bytes): string
  {
    $bytes = ltrim($bytes, "\x00");
    // A leading 1 bit would make it negative.
    if ($bytes === '' || (ord($bytes[0]) & 0x80)) {
      $bytes = "\x00" . $bytes;
    }

    return self::der(0x02, $bytes);
  }

  /**
   * Minimal CBOR reader: the definite-length subset authenticators emit
   * (integers, byte/text strings, arrays, maps, true/false/null). Advances
   * $offset past the item it read.
   *
   * @throws \RuntimeException on truncated or unsupported input
   */
  private static function cborDecode(string $data, int &$offset, int $depth = 0)
  {
    if ($depth > 8 || $offset >= strlen($data)) {
      throw new \RuntimeException('Invalid CBOR.');
    }

    $initial = ord($data[$offset++]);
    $major = $initial >> 5;
    $info = $initial & 0x1F;

    if ($major === 7) {
      return match ($info) {
        20 => false,
        21 => true,
        22 => null,
        default => throw new \RuntimeException('Unsupported CBOR value.'),
      };
    }

    if ($info < 24) {
      $value = $info;
    } else {
      $size = match ($info) {
        24 => 1,
        25 => 2,
        26 => 4,
        27 => 8,
        default => throw new \RuntimeException('Unsupported CBOR length.'),
      };
      if ($offset + $size > strlen($data)) {
        throw new \RuntimeException('Truncated CBOR.');
      }
      $value = 0;
      for ($i = 0; $i < $size; $i++) {
        $value = ($value << 8) | ord($data[$offset++]);
      }
    }

    switch ($major) {
      case 0:
        return $value;
      case 1:
        return -1 - $value;
      case 2:
      case 3:
        if ($value < 0 || $offset + $value > strlen($data)) {
          throw new \RuntimeException('Truncated CBOR.');
        }
        $string = substr($data, $offset, $value);
        $offset += $value;
        return $string;
      case 4:
        $list = [];
        for ($i = 0; $i < $value; $i++) {
          $list[] = self::cborDecode($data, $offset, $depth + 1);
        }
        return $list;
      case 5:
        $map = [];
        for ($i = 0; $i < $value; $i++) {
          $key = self::cborDecode($data, $offset, $depth + 1);
          if (!is_int($key) && !is_string($key)) {
            throw new \RuntimeException('Unsupported CBOR map key.');
          }
          $map[$key] = self::cborDecode($data, $offset, $depth + 1);
        }
        return $map;
    }

    throw new \RuntimeException('Unsupported CBOR type.');
  }

  public static function b64urlEncode(string $data): string
  {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
  }

  public static function b64urlDecode(string $data): string
  {
    return (string) base64_decode(strtr($data, '-_', '+/'));
  }

  private static function registrationKey(AuthAccount $user): string
  {
    return 'webauthn:register:' . $user->id;
  }

  private static function loginKey(string $requestId): string
  {
    return 'webauthn:login:' . hash('sha256', $requestId);
  }
}
