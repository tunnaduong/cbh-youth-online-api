<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class UniversityController extends Controller
{
    const BASE = 'https://hoctap.coccoc.com/composer/university_hub';

    const HEADERS = [
        'User-Agent'      => 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Mobile Safari/537.36',
        'Accept-Language' => 'vi-VN,vi;q=0.9,en;q=0.8',
        'Referer'         => 'https://hoctap.coccoc.com/tim-truong-dh-cd',
    ];

    // GET /v1.0/universities/options
    public function options()
    {
        $data = Cache::remember('university_options', 86400, function () {
            $res = Http::withHeaders(self::HEADERS)
                ->timeout(15)
                ->get(self::BASE, ['offset' => 'all']);
            return $res->json();
        });

        return response()->json($data);
    }

    // GET /v1.0/universities/search?q=...&autocomplete=1
    public function search(Request $request)
    {
        $q            = $request->query('q', '');
        $autocomplete = $request->query('autocomplete');

        $url = self::BASE . '/search';
        $params = ['q' => $q];
        if ($autocomplete) {
            $params['autocomplete'] = '1';
        }

        $res = Http::withHeaders(self::HEADERS)
            ->timeout(15)
            ->get($url, $params);

        return response()->json($res->json());
    }

    // GET /v1.0/universities?offset=1&city=0&major=0&...
    public function index(Request $request)
    {
        $params = $request->only(['offset', 'city', 'type', 'major', 'subjectComposition', 'score']);

        $res = Http::withHeaders(self::HEADERS)
            ->timeout(15)
            ->get(self::BASE, $params);

        return response()->json($res->json());
    }
}
