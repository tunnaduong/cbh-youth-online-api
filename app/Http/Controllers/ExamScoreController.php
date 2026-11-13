<?php

namespace App\Http\Controllers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Proxy for provincial exam-score lookup sites (Sở GD&ĐT).
 *
 * Ninh Bình (tracuudiemnb.ninhbinh.edu.vn) is a small Express app with a JSON
 * API: GET /api/config, GET /api/captcha → { id, image: "/api/captcha-image?id=…" },
 * POST /api/search { so_bao_danh, captchaId, captchaAnswer } → { diem_thi, ghi_chu }
 * or 4xx { message }. The captcha id is a self-contained encrypted token (no
 * cookies), so the app can solve it on its own and we only relay.
 *
 * Going through the API rather than calling the site from the app: it is only
 * reachable over plain HTTP (its HTTPS certificate has expired), which iOS ATS
 * and Android's cleartext policy block in release builds.
 */
class ExamScoreController extends Controller
{
  const SOURCES = [
    'ninh-binh' => 'http://tracuudiemnb.ninhbinh.edu.vn',
  ];

  // Thi vào 10 Ninh Bình: a community site (diemthininhbinh-ten.vercel.app)
  // with GET /api/tra-diem?sbd=&ma_tinh= (no captcha). ma_tinh is the first
  // two digits of the SBD, which are also the school code. School names and
  // quotas below are copied from that site's bundle (2026 intake).
  const GRADE10_BASE = 'https://diemthininhbinh-ten.vercel.app';

  const GRADE10_SCHOOLS = [
    '04' => 'THPT Dân Tộc Nội Trú Ninh Bình',
    '05' => 'THPT Đinh Tiên Hoàng',
    '06' => 'THPT B Trần Hưng Đạo',
    '07' => 'THPT Ninh Bình - Bạc Liêu',
    '08' => 'Trường PTTHSP Tràng An',
    '11' => 'THPT Gia Viễn B',
    '12' => 'THPT Gia Viễn C',
    '13' => 'THPT Nho Quan A',
    '14' => 'THPT Nho Quan B',
    '15' => 'THPT Nho Quan C',
    '16' => 'THPT A Nguyễn Huệ',
    '17' => 'THPT Ngô Thì Nhậm',
    '18' => 'THPT Yên Mô A',
    '19' => 'THPT Yên Mô B',
    '20' => 'THPT Tạ Uyên',
    '30' => 'THPT C Bình Lục',
    '31' => 'THPT A Duy Tiên',
    '32' => 'THPT B Duy Tiên',
    '33' => 'THPT A Kim Bảng',
    '34' => 'THPT B Kim Bảng',
    '35' => 'THPT C Kim Bảng',
    '36' => 'THPT Lý Thường Kiệt',
    '37' => 'THPT Bắc Lý',
    '38' => 'THPT Nam Lý',
    '39' => 'THPT Lý Nhân',
    '40' => 'THPT A Thanh Liêm',
    '41' => 'THPT B Thanh Liêm',
    '42' => 'THPT C Thanh Liêm',
    '43' => 'THPT A Phủ Lý',
    '44' => 'THPT B Phủ Lý',
    '45' => 'THPT C Phủ Lý',
    '46' => 'THPT Nguyễn Hữu Tiến',
    '47' => 'THPT Nam Cao',
    '48' => 'THPT A Nguyễn Khuyến',
    '49' => 'THPT Lê Hoàn',
    '50' => 'THPT A Trần Hưng Đạo',
    '51' => 'THPT B Nguyễn Khuyến',
    '52' => 'THPT Ngô Quyền',
    '53' => 'THPT B Nguyễn Huệ',
    '54' => 'THPT Mỹ Lộc',
    '55' => 'THPT Trần Văn Lan',
    '56' => 'THPT Hoàng Văn Thụ',
    '57' => 'THPT Lương Thế Vinh',
    '58' => 'THPT Nguyễn Bính',
    '59' => 'THPT Nguyễn Đức Thuận',
    '60' => 'THPT Tống Văn Trân',
    '61' => 'THPT Mỹ Tho',
    '62' => 'THPT Phạm Văn Nghị',
    '63' => 'THPT Đại An',
    '64' => 'THPT Đỗ Huy Liêu',
    '65' => 'THPT Lý Nhân Tông',
    '66' => 'THPT A Nghĩa Hưng',
    '67' => 'THPT B Nghĩa Hưng',
    '68' => 'THPT C Nghĩa Hưng',
    '69' => 'THPT Nghĩa Minh',
    '70' => 'THPT Trần Nhân Tông',
    '71' => 'THPT Lý Tự Trọng',
    '72' => 'THPT Nam Trực',
    '73' => 'THPT Nguyễn Du',
    '74' => 'THPT Trần Văn Bảo',
    '75' => 'THPT Trực Ninh',
    '76' => 'THPT Trực Ninh B',
    '77' => 'THPT Nguyễn Trãi',
    '78' => 'THPT Lê Quý Đôn',
    '79' => 'THPT A Hải Hậu',
    '80' => 'THPT B Hải Hậu',
    '81' => 'THPT C Hải Hậu',
    '82' => 'THPT Thịnh Long',
    '83' => 'THPT Trần Quốc Tuấn',
    '84' => 'THPT An Phúc',
    '85' => 'THPT Vũ Văn Hiếu',
    '86' => 'THPT Xuân Trường',
    '87' => 'THPT Xuân Trường B',
    '88' => 'THPT Xuân Trường C',
    '89' => 'THPT Nguyễn Trường Thúy',
    '90' => 'THPT Giao Thủy',
    '91' => 'THPT Giao Thủy B',
    '92' => 'THPT Giao Thủy C',
    '93' => 'THPT Quất Lâm',
    '94' => 'THPT Cao Phong',
    '95' => 'THPT Quang Trung',
    '96' => 'THPT Thiên Trường',
  ];

  // Chỉ tiêu tuyển sinh per school code
  const GRADE10_QUOTAS = [
    '04' => 140,
    '05' => 484,
    '06' => 484,
    '07' => 484,
    '08' => 220,
    '11' => 440,
    '12' => 352,
    '13' => 484,
    '14' => 484,
    '15' => 484,
    '16' => 484,
    '17' => 308,
    '18' => 484,
    '19' => 484,
    '20' => 396,
    '30' => 440,
    '31' => 440,
    '32' => 440,
    '33' => 396,
    '34' => 440,
    '35' => 396,
    '36' => 352,
    '37' => 396,
    '38' => 396,
    '39' => 528,
    '40' => 440,
    '41' => 396,
    '42' => 352,
    '43' => 484,
    '44' => 484,
    '45' => 308,
    '46' => 308,
    '47' => 308,
    '48' => 308,
    '49' => 352,
    '50' => 572,
    '51' => 528,
    '52' => 484,
    '53' => 396,
    '54' => 484,
    '55' => 352,
    '56' => 484,
    '57' => 396,
    '58' => 352,
    '59' => 352,
    '60' => 572,
    '61' => 528,
    '62' => 440,
    '63' => 396,
    '64' => 352,
    '65' => 352,
    '66' => 528,
    '67' => 484,
    '68' => 484,
    '69' => 308,
    '70' => 308,
    '71' => 484,
    '72' => 484,
    '73' => 396,
    '74' => 352,
    '75' => 528,
    '76' => 484,
    '77' => 396,
    '78' => 440,
    '79' => 572,
    '80' => 440,
    '81' => 484,
    '82' => 352,
    '83' => 396,
    '84' => 308,
    '85' => 396,
    '86' => 528,
    '87' => 528,
    '88' => 396,
    '89' => 396,
    '90' => 572,
    '91' => 572,
    '92' => 440,
    '93' => 396,
    '94' => 225,
    '95' => 315,
    '96' => 360,
  ];

  const HEADERS = [
    'User-Agent'      => 'Mozilla/5.0 (Linux; Android 15) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0 Mobile Safari/537.36',
    'Accept-Language' => 'vi-VN,vi;q=0.9',
  ];

  private function base(string $source): string
  {
    abort_unless(isset(self::SOURCES[$source]), 404, 'Nguồn tra cứu không tồn tại.');

    return self::SOURCES[$source];
  }

  private function http(string $source)
  {
    $base = $this->base($source);

    return Http::withHeaders(self::HEADERS + ['Referer' => $base . '/'])->timeout(15);
  }

  private function unreachable()
  {
    return response()->json(['message' => 'Không kết nối được máy chủ tra cứu điểm.'], 503);
  }

  // GET /v1.0/exam-scores/ninh-binh-10/search?sbd=...
  public function grade10(Request $request)
  {
    $validated = $request->validate([
      'sbd' => ['required', 'string', 'regex:/^\d{6,10}$/'],
    ], [
      'sbd.regex' => 'Số báo danh chỉ gồm chữ số (ít nhất 6 số).',
    ]);
    $sbd  = $validated['sbd'];
    $code = substr($sbd, 0, 2);

    if (!isset(self::GRADE10_SCHOOLS[$code])) {
      return response()->json(['message' => "Mã trường {$code} chưa có dữ liệu.", 'reason' => 'not_found'], 422);
    }

    try {
      $res = Http::withHeaders(self::HEADERS + ['Referer' => self::GRADE10_BASE . '/'])
        ->timeout(15)
        ->get(self::GRADE10_BASE . '/api/tra-diem', ['sbd' => $sbd, 'ma_tinh' => $code]);
    } catch (ConnectionException $e) {
      return $this->unreachable();
    }

    $data = $res->json();
    if ($res->status() === 404) {
      return response()->json(['message' => $data['error'] ?? 'Không tìm thấy SBD.', 'reason' => 'not_found'], 422);
    }
    if (!$res->ok() || !is_array($data) || !isset($data['tong'])) {
      return $this->unreachable();
    }

    $quota = self::GRADE10_QUOTAS[$code] ?? null;
    $rank  = isset($data['xep_hang']) ? (int) $data['xep_hang'] : null;
    // Same thresholds as the source site: top 90% of the quota = likely in,
    // up to the quota = borderline, beyond = likely out.
    $chance = null;
    if ($quota && $rank) {
      $chance = $rank <= 0.9 * $quota ? 'likely' : ($rank <= $quota ? 'borderline' : 'unlikely');
    }

    return response()->json([
      'sbd'         => $data['sbd'] ?? $sbd,
      'school_code' => $code,
      'school_name' => self::GRADE10_SCHOOLS[$code],
      'van'         => $data['van'] ?? null,
      'toan'        => $data['toan'] ?? null,
      'ngoai_ngu'   => $data['ngoai_ngu'] ?? null,
      'tong'        => $data['tong'] ?? null,
      'xep_hang'    => $rank,
      'tong_so'     => isset($data['tong_so']) ? (int) $data['tong_so'] : null,
      'hang_van'    => $data['hang_van'] ?? null,
      'hang_toan'   => $data['hang_toan'] ?? null,
      'hang_nn'     => $data['hang_nn'] ?? null,
      'chi_tieu'    => $quota,
      'chance'      => $chance,
    ]);
  }

  // GET /v1.0/exam-scores/{source}/config
  public function config(string $source)
  {
    $base = $this->base($source);

    try {
      $config = Cache::remember("exam_scores_config_{$source}", 300, function () use ($source, $base) {
        $res = $this->http($source)->get($base . '/api/config');
        if (!$res->ok() || !is_array($res->json())) {
          throw new \RuntimeException('bad config');
        }

        return $res->json();
      });
    } catch (\Throwable $e) {
      return $this->unreachable();
    }

    return response()->json([
      'name'        => $config['name'] ?? null,
      'title'       => $config['title'] ?? null,
      'school_year' => $config['school_year'] ?? null,
      // "2026-06-01T08:00" in Vietnam time, no offset given.
      'start_time'  => !empty($config['start_time']) ? $config['start_time'] . (strlen($config['start_time']) === 16 ? ':00+07:00' : '') : null,
      'pending_msg' => $config['pending_msg'] ?? null,
      'source_url'  => $base . '/',
    ]);
  }

  // GET /v1.0/exam-scores/{source}/captcha → { id, svg }
  public function captcha(string $source)
  {
    $base = $this->base($source);

    try {
      $res = $this->http($source)->get($base . '/api/captcha');
      $data = $res->json();
      if (!$res->ok() || empty($data['id']) || empty($data['image'])) {
        return $this->unreachable();
      }

      $image = $data['image'];
      if (str_starts_with($image, '/')) {
        $image = $base . $image;
      }
      $img = $this->http($source)->get($image);
      if (!$img->ok()) {
        return $this->unreachable();
      }
    } catch (ConnectionException $e) {
      return $this->unreachable();
    }

    return response()
      ->json(['id' => $data['id'], 'svg' => $this->normalizeSvg($img->body())])
      ->header('Cache-Control', 'no-store');
  }

  // POST /v1.0/exam-scores/{source}/search { so_bao_danh, captcha_id, captcha_answer }
  public function search(Request $request, string $source)
  {
    $base = $this->base($source);

    $validated = $request->validate([
      'so_bao_danh'    => 'required|string|max:32',
      'captcha_id'     => 'required|string|max:512',
      'captcha_answer' => 'required|string|max:16',
    ]);

    try {
      $res = $this->http($source)->asJson()->post($base . '/api/search', [
        'so_bao_danh'   => strtoupper(trim($validated['so_bao_danh'])),
        'captchaId'     => $validated['captcha_id'],
        'captchaAnswer' => strtoupper(trim($validated['captcha_answer'])),
      ]);
    } catch (ConnectionException $e) {
      return $this->unreachable();
    }

    $data = $res->json();
    if (!is_array($data)) {
      return $this->unreachable();
    }

    if (!$res->successful()) {
      $message = $data['message'] ?? 'Lỗi tra cứu điểm!';

      return response()->json([
        'message' => $message,
        // Lets the app tell a wrong/expired captcha (focus the code
        // field) from "no such candidate" without matching on text.
        'reason'  => str_contains(mb_strtolower($message), 'mã xác nhận') ? 'captcha' : 'not_found',
      ], $res->status() >= 500 ? 503 : 422);
    }

    return response()->json([
      'so_bao_danh' => strtoupper(trim($validated['so_bao_danh'])),
      'diem_thi'    => $data['diem_thi'] ?? null,
      'subjects'    => $this->parseScores($data['diem_thi'] ?? ''),
      'ghi_chu'     => $data['ghi_chu'] ?? null,
      'raw'         => $data,
    ]);
  }

  /**
   * "Toán: 8.5   Ngữ văn: 7.25\nTiếng Anh: 9" → [{ name, score }, ...].
   * Same split the site's own formatDiemThi() uses: 2+ spaces, newline or ";".
   */
  private function parseScores(string $text): array
  {
    if (!str_contains($text, ':')) {
      return [];
    }

    $subjects = [];
    foreach (preg_split('/\s{2,}|\n|;/u', $text) as $pair) {
      $pair = trim($pair);
      if ($pair === '') {
        continue;
      }
      $pos = mb_strpos($pair, ':');
      if ($pos === false) {
        $subjects[] = ['name' => $pair, 'score' => null];
        continue;
      }
      $subjects[] = [
        'name'  => trim(mb_substr($pair, 0, $pos)),
        'score' => trim(mb_substr($pair, $pos + 1)),
      ];
    }

    return $subjects;
  }

  /**
   * The captcha draws each glyph with dominant-baseline="middle", which
   * react-native-svg ignores (glyphs would sit on the baseline and be cut
   * off at the top). Move each glyph down by ~0.35em instead.
   */
  private function normalizeSvg(string $svg): string
  {
    return preg_replace_callback('/<text\b[^>]*>/i', function ($m) {
      $tag = $m[0];
      if (!preg_match('/dominant-baseline="middle"/i', $tag)) {
        return $tag;
      }
      preg_match('/font-size="([\d.]+)"/', $tag, $fs);
      $shift = isset($fs[1]) ? (float) $fs[1] * 0.35 : 8;
      $tag = preg_replace('/\s*dominant-baseline="middle"/i', '', $tag);

      return preg_replace_callback('/\by="([\d.\-]+)"/', fn($y) => 'y="' . round((float) $y[1] + $shift, 2) . '"', $tag, 1);
    }, $svg);
  }
}
