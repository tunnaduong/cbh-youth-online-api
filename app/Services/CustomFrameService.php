<?php

namespace App\Services;

use App\Models\AuthAccount;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;

/**
 * The image a member uploads as their own avatar frame or profile frame
 * (ProfileThemeService's "custom" option, Pro Plus tier).
 *
 * An uploaded picture is drawn on top of other content, so the rules exist
 * to keep that content visible:
 *   - avatar frame: drawn centred on the avatar at SCALE times its size, so
 *     the avatar fills the middle 80% of the image. The centre circle
 *     (HOLE of the width) has to be transparent - the frame may overlap the
 *     avatar's rim, never the face.
 *   - profile frame: drawn as a nine-slice border - only the outer SLICE of
 *     each side is used (corners keep their shape, edges stretch), because
 *     the cover it goes around is a different shape on every screen. The
 *     middle of the image is never drawn, so nothing can cover the cover.
 * Both have to be square with something visible in the part that is drawn.
 *
 * Every image is re-encoded here (fixed size, PNG): that drops metadata and
 * any animation, and means the stored file is one this server produced.
 */
class CustomFrameService
{
  public const MAX_BYTES = 5 * 1024 * 1024;

  /** Smallest / largest side of an upload, in pixels. */
  public const MIN_SIZE = 256;
  public const MAX_SIZE = 2048;

  /** How far from square an upload may be (it is centre-cropped to square). */
  public const SQUARE_TOLERANCE = 0.1;

  public const KINDS = [
    'avatar' => [
      // Side of the stored image.
      'size' => 320,
      // Drawn at this multiple of the avatar's size.
      'scale' => 1.25,
      // Diameter of the centre circle that must stay transparent, as a part
      // of the image's width.
      'hole' => 0.64,
      // At most this part of the centre circle may be painted.
      'hole_max_painted' => 0.1,
      // At least this part of the rest has to be painted.
      'min_painted' => 0.03,
    ],
    'profile' => [
      'size' => 480,
      // Width of the border band on each side, as a part of the image's width.
      'slice' => 0.25,
      'min_painted' => 0.05,
    ],
  ];

  /** A pixel counts as painted above this opacity (0 = clear, 1 = solid). */
  private const PAINTED = 0.1;

  /** Pixels are checked on a grid this many pixels apart. */
  private const STEP = 4;

  /**
   * The limits, for the clients' editors (shown to the member and checked
   * before uploading).
   */
  public static function rules(): array
  {
    return [
      'formats' => ['png', 'webp'],
      'max_bytes' => self::MAX_BYTES,
      'min_size' => self::MIN_SIZE,
      'avatar' => [
        'size' => self::KINDS['avatar']['size'],
        'scale' => self::KINDS['avatar']['scale'],
        'hole' => self::KINDS['avatar']['hole'],
      ],
      'profile' => [
        'size' => self::KINDS['profile']['size'],
        'slice' => self::KINDS['profile']['slice'],
      ],
    ];
  }

  /**
   * Check an upload against the rules, store it as the user's frame of that
   * kind and delete the one it replaces.
   *
   * @return array{ok: bool, message?: string, url?: string}
   */
  public static function store(AuthAccount $user, string $kind, UploadedFile $file): array
  {
    $fail = fn(string $message) => ['ok' => false, 'message' => $message];
    $spec = self::KINDS[$kind];

    if ($file->getSize() > self::MAX_BYTES) {
      return $fail('Ảnh khung tối đa 5MB.');
    }

    $dimensions = @getimagesize($file->getRealPath());
    if (!$dimensions || !in_array($dimensions[2], [IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
      return $fail('Ảnh khung phải là PNG hoặc WebP có nền trong suốt.');
    }

    [$width, $height] = $dimensions;
    if (min($width, $height) < self::MIN_SIZE) {
      return $fail('Ảnh khung cần có cạnh tối thiểu ' . self::MIN_SIZE . 'px.');
    }
    if (max($width, $height) > self::MAX_SIZE) {
      return $fail('Ảnh khung có cạnh tối đa ' . self::MAX_SIZE . 'px.');
    }
    if (abs($width / $height - 1) > self::SQUARE_TOLERANCE) {
      return $fail('Ảnh khung cần có dạng hình vuông.');
    }

    try {
      $image = Image::make($file->getRealPath())->fit($spec['size'], $spec['size']);
      $core = $image->getCore();
      // GD keeps opacity in the top 7 bits of a pixel: 0 = solid, 127 = clear.
      $opacity = fn(int $x, int $y) => 1 - ((imagecolorat($core, $x, $y) >> 24) & 0x7F) / 127;

      $error = self::check($kind, $spec['size'], $opacity);
      if ($error !== null) {
        return $fail($error);
      }

      $path = 'frames/' . $kind . '/' . $user->id . '_' . Str::random(20) . '.png';
      Storage::disk('public')->put($path, (string) $image->encode('png'));
    } catch (\Throwable $e) {
      // An animated WebP, a broken file, or a server without WebP support.
      Log::warning('Could not process a custom frame', ['user' => $user->id, 'kind' => $kind, 'error' => $e->getMessage()]);

      return $fail('Không đọc được ảnh này. Hãy dùng ảnh PNG tĩnh có nền trong suốt.');
    }

    $column = self::column($kind);
    $previous = $user->profile->getAttribute($column);

    try {
      $user->profile->forceFill([$column => $path])->save();
    } catch (\Throwable $e) {
      // The migration adding the columns has not run yet.
      Storage::disk('public')->delete($path);
      Log::warning('Could not save a custom frame', ['user' => $user->id, 'error' => $e->getMessage()]);

      return $fail('Tính năng khung tùy chỉnh chưa sẵn sàng, vui lòng thử lại sau.');
    }

    self::deleteFile($previous);

    return ['ok' => true, 'url' => ProfileThemeService::customFrameUrl($user, $kind)];
  }

  /**
   * Remove the user's frame of that kind: the file, and the "custom" choice
   * in their saved theme (it would name an image that is gone).
   *
   * @return bool whether there was one
   */
  public static function remove(AuthAccount $user, string $kind): bool
  {
    $profile = $user->profile;
    $column = self::column($kind);
    $path = $profile?->getAttribute($column);
    if (!is_string($path) || $path === '') {
      return false;
    }

    $field = ProfileThemeService::CUSTOM_FRAME_FIELDS[$kind];
    $theme = $profile->profile_theme;
    if (is_array($theme) && ($theme[$field] ?? null) === 'custom') {
      $theme[$field] = array_key_first(ProfileThemeService::OPTIONS[$field]);
      $profile->profile_theme = $theme;
    }

    $profile->forceFill([$column => null])->save();
    self::deleteFile($path);

    return true;
  }

  /**
   * The rule an image of $size x $size breaks, as a message for the member,
   * or null when it is a usable frame. $opacity(x, y) gives a pixel's
   * opacity from 0 (clear) to 1 (solid).
   */
  public static function check(string $kind, int $size, callable $opacity): ?string
  {
    $spec = self::KINDS[$kind];
    $centre = ($size - 1) / 2;
    $holeRadius = $size * ($spec['hole'] ?? 0) / 2;
    $band = $size * ($spec['slice'] ?? 0);

    $inside = $insidePainted = $drawn = $drawnPainted = 0;

    for ($y = 0; $y < $size; $y += self::STEP) {
      for ($x = 0; $x < $size; $x += self::STEP) {
        $painted = $opacity($x, $y) > self::PAINTED;

        if ($kind === 'avatar') {
          if (hypot($x - $centre, $y - $centre) <= $holeRadius) {
            $inside++;
            $insidePainted += $painted ? 1 : 0;
            continue;
          }
        } elseif ($x >= $band && $x < $size - $band && $y >= $band && $y < $size - $band) {
          // The middle of a profile frame is never drawn.
          continue;
        }

        $drawn++;
        $drawnPainted += $painted ? 1 : 0;
      }
    }

    if ($kind === 'avatar' && $inside > 0 && $insidePainted / $inside > $spec['hole_max_painted']) {
      return 'Phần giữa của khung avatar phải trong suốt để không che ảnh đại diện (vòng tròn ở giữa rộng '
        . round($spec['hole'] * 100) . '% chiều rộng ảnh).';
    }

    if ($drawn === 0 || $drawnPainted / $drawn < $spec['min_painted']) {
      return $kind === 'avatar'
        ? 'Ảnh khung gần như trống. Hãy vẽ khung ở phần viền quanh vòng tròn giữa.'
        : 'Ảnh khung gần như trống. Hãy vẽ khung ở phần viền ngoài (' . round($spec['slice'] * 100) . '% mỗi cạnh).';
    }

    return null;
  }

  private static function column(string $kind): string
  {
    return 'custom_' . $kind . '_frame';
  }

  private static function deleteFile($path): void
  {
    // Only ever a file this service stored.
    if (is_string($path) && str_starts_with($path, 'frames/')) {
      Storage::disk('public')->delete($path);
    }
  }
}
