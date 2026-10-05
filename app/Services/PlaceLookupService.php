<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Looks a named place up on the map, for the gift shop assistant: when a
 * customer gives a landmark as the delivery address ("Trường THPT chuyên
 * Biên Hòa, Phủ Lý") it checks that the place exists and whether several
 * places share the name (two campuses, same-named schools in two towns).
 *
 * Uses Photon (photon.komoot.io), a free search over OpenStreetMap data that
 * needs no key. It is a map search, not a general web search, and the map
 * has gaps - "nothing found" does not mean the place doesn't exist, which
 * the assistant's prompt accounts for.
 */
class PlaceLookupService
{
  private const API_URL = 'https://photon.komoot.io/api/';

  // Rough box around Vietnam (west, south, east, north), to keep foreign
  // places with a similar name out.
  private const VIETNAM_BBOX = '102.1,8.1,109.6,23.5';

  /**
   * Best guess at where an address is, to open the customer's map there:
   * the first match, or null when nothing was found or the lookup failed.
   * Only a starting point - the customer still confirms the spot themselves.
   *
   * @return array{lat: float, lng: float}|null
   */
  public function locate(string $address): ?array
  {
    $first = $this->search($address)['places'][0] ?? null;

    return $first && $first['lat'] !== null && $first['lng'] !== null
      ? ['lat' => $first['lat'], 'lng' => $first['lng']]
      : null;
  }

  /**
   * @return array{ok: bool, places: array<int, array{name: string, kind: ?string, where: string, lat: ?float, lng: ?float}>}
   *   `ok` is false when the lookup itself failed (network, timeout), so the
   *   caller can tell "couldn't check" from "checked, found nothing".
   */
  public function search(string $query): array
  {
    try {
      $response = Http::timeout(8)
        ->withHeaders(['User-Agent' => 'CBHYouthOnline-Giftshop/1.0 (https://giftshop.chuyenbienhoa.com)'])
        ->get(self::API_URL, [
          'q' => $query,
          'limit' => 8,
          'bbox' => self::VIETNAM_BBOX,
        ]);

      if (!$response->successful()) {
        throw new \RuntimeException('HTTP ' . $response->status());
      }

      $places = [];
      foreach ($response->json('features') ?? [] as $feature) {
        $p = $feature['properties'] ?? [];
        if (($p['countrycode'] ?? null) !== 'VN' || empty($p['name'])) {
          continue;
        }

        // Street first, then each wider area once.
        $where = collect([
          trim(($p['housenumber'] ?? '') . ' ' . ($p['street'] ?? '')),
          $p['locality'] ?? null,
          $p['district'] ?? null,
          $p['city'] ?? null,
          $p['county'] ?? null,
          $p['state'] ?? null,
        ])->filter()->unique()->implode(', ');

        $key = mb_strtolower($p['name'] . '|' . $where);
        $places[$key] = [
          'name' => $p['name'],
          // OpenStreetMap's own category: school, university, hospital, marketplace...
          'kind' => $p['osm_value'] ?? null,
          'where' => $where,
          'lat' => isset($feature['geometry']['coordinates'][1]) ? (float) $feature['geometry']['coordinates'][1] : null,
          'lng' => isset($feature['geometry']['coordinates'][0]) ? (float) $feature['geometry']['coordinates'][0] : null,
        ];
      }

      return ['ok' => true, 'places' => array_slice(array_values($places), 0, 6)];
    } catch (\Throwable $e) {
      Log::warning('Place lookup failed: ' . $e->getMessage());

      return ['ok' => false, 'places' => []];
    }
  }
}
