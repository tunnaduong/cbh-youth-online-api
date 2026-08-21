<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Small (480px) preview assets for uploaded images/videos - shared by chat
 * (ChatController) and posts/stories (FileUploadController), so a bubble or
 * a feed card never has to decode a full-resolution original just to render
 * a few hundred pixels of it. Both methods return a *relative* disk path
 * (matching the convention the rest of the upload pipeline already uses),
 * or null if generation failed - callers should fall back to the original
 * file in that case, not fail the whole upload over a missing thumbnail.
 */
class MediaThumbnailService
{
  public static function imageThumbnail(string $imagePath, string $destFolder = 'thumbnails/images'): ?string
  {
    $disk = Storage::disk('public');
    $thumbPath = $destFolder . '/' . Str::uuid() . '.jpg';

    try {
      $image = \Intervention\Image\Facades\Image::make($disk->path($imagePath));
      $image->orientate();
      if ($image->width() > 480) {
        $image->resize(480, null, function ($constraint) {
          $constraint->aspectRatio();
          $constraint->upsize();
        });
      }

      $disk->makeDirectory($destFolder);
      $disk->put($thumbPath, (string) $image->encode('jpg', 75));

      return $thumbPath;
    } catch (\Throwable $exception) {
      Log::warning('MediaThumbnailService: unable to create image thumbnail', [
        'image_path' => $imagePath,
        'error' => $exception->getMessage(),
      ]);

      return null;
    }
  }

  public static function videoFirstFrame(string $videoPath, string $destFolder = 'thumbnails/video-frames'): ?string
  {
    $disk = Storage::disk('public');
    $framePath = $destFolder . '/' . Str::uuid() . '.jpg';
    $inputPath = $disk->path($videoPath);
    $outputPath = $disk->path($framePath);

    $disk->makeDirectory($destFolder);

    try {
      $process = new \Symfony\Component\Process\Process([
        env('FFMPEG_BINARY', 'ffmpeg'),
        '-y',
        '-i',
        $inputPath,
        '-vframes',
        '1',
        '-vf',
        'scale=480:-1:flags=lanczos',
        $outputPath,
      ]);
      $process->setTimeout(60);
      $process->run();

      if (!$process->isSuccessful() || !$disk->exists($framePath)) {
        Log::warning('MediaThumbnailService: unable to create video first frame', [
          'video_path' => $videoPath,
          'error' => trim($process->getErrorOutput()),
        ]);

        return null;
      }

      return $framePath;
    } catch (\Throwable $exception) {
      Log::warning('MediaThumbnailService: error creating video first frame', [
        'video_path' => $videoPath,
        'error' => $exception->getMessage(),
      ]);

      return null;
    }
  }

  /**
   * Small, muted, low-bitrate copy of a video for inline/feed autoplay -
   * decode cost scales with source resolution regardless of display size,
   * so autoplaying a full-resolution original in a small card/bubble burns
   * far more CPU than necessary. No audio track: none of this app's inline
   * autoplay players expose sound controls.
   */
  public static function videoPreview(string $videoPath, string $destFolder = 'thumbnails/video-previews'): ?string
  {
    $disk = Storage::disk('public');
    $previewPath = $destFolder . '/' . Str::uuid() . '.mp4';
    $inputPath = $disk->path($videoPath);
    $outputPath = $disk->path($previewPath);

    $disk->makeDirectory($destFolder);

    $cmd = sprintf(
      'ffmpeg -y -i %s -an -c:v libx264 -b:v 700k -maxrate 700k -bufsize 1400k -vf %s -movflags +faststart %s 2>&1',
      escapeshellarg($inputPath),
      escapeshellarg("scale='if(gt(iw\\,480)\\,480\\,iw)':-2:force_original_aspect_ratio=decrease:flags=lanczos"),
      escapeshellarg($outputPath)
    );

    exec($cmd, $output, $returnCode);

    if ($returnCode !== 0 || !file_exists($outputPath)) {
      Log::warning('MediaThumbnailService: ffmpeg failed generating video preview', [
        'video_path' => $videoPath,
        'output' => implode("\n", array_slice($output, -20)),
      ]);

      return null;
    }

    return $previewPath;
  }

  /**
   * Same "make Storage::url() absolute" logic as
   * ChatController::absoluteStorageUrl - duplicated rather than shared
   * because that one's a private controller method and jobs/services calling
   * in from outside a request need their own copy.
   */
  public static function absoluteUrl(?string $path): ?string
  {
    if (!$path) {
      return null;
    }

    $url = Storage::url($path);

    if (Str::startsWith($url, ['http://', 'https://'])) {
      return $url;
    }

    return rtrim(config('app.url'), '/') . $url;
  }
}
