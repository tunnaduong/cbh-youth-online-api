<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * A customer's saved delivery details (recipient, phone, address) for the
 * gift shop. Written by the support assistant once it has collected them in
 * the chat (GenerateAiChatReply), read back into its context on later orders.
 */
class ShopAddress extends Model
{
  protected $table = 'cyo_shop_addresses';

  /** How many a customer keeps; the least recently used go first. */
  public const MAX_PER_USER = 8;

  protected $fillable = [
    'user_id', 'recipient_name', 'phone', 'address',
    'place', 'street', 'ward', 'district', 'province',
    'fingerprint', 'last_used_at',
  ];

  protected $casts = [
    'last_used_at' => 'datetime',
  ];

  /**
   * Checks and tidies delivery details the assistant produced. Returns the
   * clean values, or null with the reason (written for the customer) when
   * they aren't complete enough to deliver to.
   *
   * @return array{0: ?array{recipient_name: string, phone: string, address: string, place: ?string, street: ?string, ward: ?string, district: ?string, province: ?string}, 1: ?string}
   */
  public static function parse(array $raw): array
  {
    $name = self::clean($raw['recipient_name'] ?? '', 120);
    $phone = preg_replace('/[\s.\-()]/', '', (string) ($raw['phone'] ?? ''));
    $phone = preg_replace('/^\+?84(?=\d{9}$)/', '0', $phone);
    $address = self::clean($raw['address'] ?? '', 500);

    // A reused address from an earlier chat order may still start with the
    // recipient ("Name - address"): don't end up with the name twice.
    if ($name !== '' && mb_stripos($address, $name . ' - ') === 0) {
      $address = trim(mb_substr($address, mb_strlen($name) + 3));
    }

    if (mb_strlen($name) < 2) {
      return [null, 'thiếu họ tên người nhận.'];
    }
    if (!preg_match('/^0\d{8,10}$/', $phone)) {
      return [null, 'số điện thoại người nhận chưa hợp lệ.'];
    }
    if (mb_strlen($address) < 10) {
      return [null, 'địa chỉ giao hàng chưa đầy đủ.'];
    }

    $part = fn(string $key, int $max) => self::clean($raw[$key] ?? '', $max) ?: null;

    return [[
      'recipient_name' => $name,
      'phone' => $phone,
      'address' => $address,
      'place' => $part('place', 200),
      'street' => $part('street', 200),
      'ward' => $part('ward', 120),
      'district' => $part('district', 120),
      'province' => $part('province', 120),
    ], null];
  }

  /**
   * Saves parsed details for a customer, or marks them as just used when
   * the same recipient + phone + address is already there. Never throws:
   * remembering an address must not get in the way of answering the
   * customer (the table may not exist yet on a server that hasn't migrated).
   *
   * @param  array  $details  The first value returned by parse().
   */
  public static function remember(int $userId, array $details): void
  {
    try {
      $fingerprint = md5(mb_strtolower(preg_replace(
        '/\s+/u',
        ' ',
        $details['recipient_name'] . '|' . $details['phone'] . '|' . $details['address']
      )));

      $existing = self::where('user_id', $userId)->where('fingerprint', $fingerprint)->first();
      if ($existing) {
        // Keep the newest split of the address if this one has more of it.
        $existing->fill(array_filter(
          array_intersect_key($details, array_flip(['place', 'street', 'ward', 'district', 'province'])),
          fn($value) => $value !== null
        ));
        $existing->last_used_at = now();
        $existing->save();

        return;
      }

      self::create($details + [
        'user_id' => $userId,
        'fingerprint' => $fingerprint,
        'last_used_at' => now(),
      ]);

      $stale = self::where('user_id', $userId)
        ->orderByDesc('last_used_at')
        ->orderByDesc('id')
        ->skip(self::MAX_PER_USER)
        ->take(50)
        ->pluck('id');
      if ($stale->isNotEmpty()) {
        self::whereIn('id', $stale)->delete();
      }
    } catch (\Throwable $e) {
      Log::warning('Could not save shop address: ' . $e->getMessage());
    }
  }

  /**
   * A customer's saved details, most recently used first. Empty (not an
   * error) when the table isn't there yet.
   *
   * @return \Illuminate\Support\Collection<int, self>
   */
  public static function forCustomer(int $userId)
  {
    try {
      return self::where('user_id', $userId)
        ->orderByDesc('last_used_at')
        ->orderByDesc('id')
        ->limit(self::MAX_PER_USER)
        ->get();
    } catch (\Throwable $e) {
      return collect();
    }
  }

  private static function clean($value, int $max): string
  {
    $value = is_scalar($value) ? (string) $value : '';

    return mb_substr(trim(preg_replace('/\s+/u', ' ', $value)), 0, $max);
  }
}
