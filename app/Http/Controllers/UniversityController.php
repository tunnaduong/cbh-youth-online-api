<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class UniversityController extends Controller
{
    const BASE = 'https://hoctap.coccoc.com/composer/university_hub';

    const HEADERS = [
        'User-Agent'      => 'Mozilla/5.0 (Linux; Android 15; Pixel 9 Pro XL Build/AP4A.241205.013) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/174.10.5.6 Mobile Safari/537.36',
        'Accept-Language' => 'vi-VN,vi;q=0.9,en;q=0.8',
        'Referer'         => 'https://hoctap.coccoc.com/tim-truong-dh-cd',
    ];

    // Clean a single university object: strip HTML from address, deduplicate URLs
    private function normalizeUniversity(array $uni): array
    {
        // Strip HTML from address: <br> → newline, remove all other tags
        if (!empty($uni['address'])) {
            $uni['address'] = preg_replace('/<br\s*\/?>/i', "\n", $uni['address']);
            $uni['address'] = trim(strip_tags($uni['address']));
        }

        // Deduplicate URLs
        if (!empty($uni['url'])) {
            $urls = preg_split('/\s+/', trim($uni['url']));
            $urls = array_values(array_unique(array_filter($urls, fn($u) => str_starts_with($u, 'http'))));
            $uni['urls'] = $urls;
            unset($uni['url']);
        } else {
            $uni['urls'] = [];
        }

        return $uni;
    }

    // GET /v1.0/universities/options
    public function options()
    {
        // Filter choices (city/major/type/subjectComposition) barely ever
        // change, so they're cached. generalInfo ("Quy chế tuyển sinh") is
        // fetched fresh on every request so newly published notices show up
        // immediately instead of waiting out the cache TTL.
        $filters = Cache::remember('university_filters', 86400, function () {
            $res = Http::withHeaders(self::HEADERS)
                ->timeout(15)
                ->get(self::BASE, ['offset' => 'all']);
            $uh = $res->json()['verticals_university_hub']['university_hub'] ?? null;

            return [
                'city'               => $uh['city'] ?? [],
                'major'              => $uh['major'] ?? [],
                'type'               => $uh['type'] ?? [],
                'subjectComposition' => $uh['subjectComposition'] ?? [],
            ];
        });

        $res = Http::withHeaders(self::HEADERS)
            ->timeout(15)
            ->get(self::BASE, ['offset' => 'all']);
        $uh = $res->json()['verticals_university_hub']['university_hub'] ?? null;

        return response()->json([
            ...$filters,
            'generalInfo' => $uh['generalInfo'] ?? [],
        ]);
    }

    // GET /v1.0/universities/search?q=...&autocomplete=1
    public function search(Request $request)
    {
        $q            = $request->query('q', '');
        $autocomplete = $request->query('autocomplete');

        $params = ['q' => $q];
        if ($autocomplete) {
            $params['autocomplete'] = '1';
        }

        $res = Http::withHeaders(self::HEADERS)
            ->timeout(15)
            ->get(self::BASE . '/search', $params);

        $raw = $res->json();
        $list = $raw['verticals_university_hub']['university_hub'] ?? [];

        if (!is_array($list)) {
            $list = [];
        }

        // autocomplete=1 returns an array of plain name strings; a normal
        // search returns full university objects — normalize only the latter.
        $list = array_map(
            fn($u) => is_array($u) ? $this->normalizeUniversity($u) : ['name' => $u],
            $list
        );

        return response()->json($list);
    }

    // GET /v1.0/universities?offset=1&city=0&major=0&...
    public function index(Request $request)
    {
        $params = $request->only(['offset', 'city', 'type', 'major', 'subjectComposition', 'score']);

        $res = Http::withHeaders(self::HEADERS)
            ->timeout(15)
            ->get(self::BASE, $params);

        $raw = $res->json();
        $uh  = $raw['verticals_university_hub']['university_hub'] ?? [];

        $universities = array_map(
            fn($u) => $this->normalizeUniversity($u),
            $uh['universityResponses'] ?? []
        );

        return response()->json([
            'universities' => $universities,
            'currentPage'  => $uh['currentPage'] ?? 1,
            'maxPage'      => $uh['maxPage'] ?? 1,
        ]);
    }
}
