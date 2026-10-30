<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Controller for self-hosted Over-The-Air (OTA) updates using the Expo Updates Protocol (v1).
 *
 * Implements:
 * 1. GET /v1.0/ota/manifest: Serves update manifests or directives (noUpdateAvailable / rollBackToEmbedded)
 *    in multipart/mixed format as expected by expo-updates.
 * 2. GET /v1.0/ota/assets/{channel}/{runtimeVersion}/{updateId}/{path}: Streams bundle JS and static assets
 *    with immutable cache headers.
 * 3. POST /v1.0/admin/ota/publish: Uploads an exported Expo dist zip archive to publish a new update.
 * 4. POST /v1.0/admin/ota/rollback: Immediately rolls back to embedded binary or a previous update.
 * 5. GET /v1.0/admin/ota/history: Lists deployment history.
 */
class OtaController extends Controller
{
  /**
   * Serve the update manifest or directive to the mobile app.
   */
  public function manifest(Request $request)
  {
    $platform = strtolower((string) ($request->header('expo-platform') ?: $request->query('platform') ?: 'android'));
    if (!in_array($platform, ['android', 'ios'], true)) {
      $platform = 'android';
    }

    $runtimeVersion = $this->sanitizeRuntimeVersion((string) ($request->header('expo-runtime-version') ?: $request->query('runtime-version') ?: '7.4.10'));
    $channel = $this->sanitizeChannel((string) ($request->header('expo-channel-name') ?: $request->query('channel') ?: 'production'));
    $currentUpdateId = (string) ($request->header('expo-current-update-id') ?: $request->query('current_update_id') ?: '');

    $otaDir = storage_path("app/ota/{$channel}/{$runtimeVersion}");
    $currentFile = "{$otaDir}/current.json";

    // If no update is published for this channel & runtime version yet
    if (!file_exists($currentFile)) {
      return $this->returnDirective($request, 'noUpdateAvailable');
    }

    $current = json_decode((string) file_get_contents($currentFile), true);
    if (!is_array($current) || empty($current['update_id'])) {
      return $this->returnDirective($request, 'noUpdateAvailable');
    }

    // Check if the current channel is marked for roll back to embedded binary
    if (!empty($current['rollback'])) {
      $commitTime = !empty($current['created_at'])
        ? \Illuminate\Support\Carbon::parse($current['created_at'])->utc()->format('Y-m-d\TH:i:s.v\Z')
        : now('UTC')->format('Y-m-d\TH:i:s.v\Z');

      return $this->returnDirective($request, 'rollBackToEmbedded', [
        'parameters' => [
          'commitTime' => $commitTime,
        ],
      ]);
    }

    // If client is already running the current active update
    if (!empty($currentUpdateId) && hash_equals($current['update_id'], $currentUpdateId)) {
      return $this->returnDirective($request, 'noUpdateAvailable');
    }

    $updateId = $current['update_id'];
    $manifestPath = "{$otaDir}/{$updateId}/manifest_{$platform}.json";
    if (!file_exists($manifestPath)) {
      return $this->returnDirective($request, 'noUpdateAvailable');
    }

    $manifest = json_decode((string) file_get_contents($manifestPath), true);
    if (!is_array($manifest)) {
      return $this->returnDirective($request, 'noUpdateAvailable');
    }

    // Ensure createdAt strictly adheres to ISO-8601 with milliseconds and UTC "Z" (required by RCTConvert NSDate:)
    if (isset($manifest['createdAt'])) {
      $manifest['createdAt'] = \Illuminate\Support\Carbon::parse($manifest['createdAt'])->utc()->format('Y-m-d\TH:i:s.v\Z');
    }

    // Rewrite URLs dynamically to match incoming request host (works for localhost, staging, production)
    $baseUrl = rtrim($request->schemeAndHttpHost(), '/') . "/v1.0/ota/assets/{$channel}/{$runtimeVersion}/{$updateId}/";
    if (isset($manifest['launchAsset']['path'])) {
      $manifest['launchAsset']['url'] = $baseUrl . ltrim($manifest['launchAsset']['path'], '/');
      unset($manifest['launchAsset']['path']);
    }

    if (isset($manifest['assets']) && is_array($manifest['assets'])) {
      foreach ($manifest['assets'] as &$asset) {
        if (isset($asset['path'])) {
          $asset['url'] = $baseUrl . ltrim($asset['path'], '/');
          unset($asset['path']);
        }
      }
      unset($asset);
    }

    return $this->returnManifest($request, $manifest);
  }

  /**
   * Serve a static bundle or asset file with immutable cache header.
   */
  public function asset(Request $request, string $channel, string $runtimeVersion, string $updateId, string $path)
  {
    $cleanChannel = $this->sanitizeChannel($channel);
    $cleanRuntime = $this->sanitizeRuntimeVersion($runtimeVersion);
    $cleanUpdateId = preg_replace('/[^a-zA-Z0-9_\-]/', '', $updateId);

    // Sanitize path against directory traversal
    $cleanPath = str_replace(['..', '\\'], ['', '/'], $path);
    $baseDir = storage_path("app/ota/{$cleanChannel}/{$cleanRuntime}/{$cleanUpdateId}");
    $filePath = "{$baseDir}/{$cleanPath}";

    $realBase = realpath($baseDir);
    $realFile = realpath($filePath);

    if (!$realBase || !$realFile || !str_starts_with($realFile, $realBase) || !is_file($realFile)) {
      abort(404, 'Asset not found');
    }

    $mime = $this->guessMimeType($cleanPath);

    return response()->file($realFile, [
      'Content-Type' => $mime,
      'Cache-Control' => 'public, max-age=31536000, immutable',
    ]);
  }

  /**
   * Publish a new OTA bundle update.
   */
  public function publish(Request $request)
  {
    $this->authorizeOtaAdmin($request);

    $channel = $this->sanitizeChannel((string) $request->input('channel', 'production'));
    $runtimeVersion = $this->sanitizeRuntimeVersion((string) $request->input('runtime_version', '7.4.10'));
    $message = (string) $request->input('message', 'OTA update via API');
    $buildNumber = (int) $request->input('build_number', 0);
    $minNativeBuild = (int) $request->input('min_native_build', 0);

    $file = $request->file('archive') ?: $request->file('file');
    if (!$file || !$file->isValid()) {
      return response()->json(['error' => 'A valid .zip archive of the exported dist folder is required.'], 422);
    }

    $updateId = (string) Str::uuid();
    $createdAt = now('UTC')->format('Y-m-d\TH:i:s.v\Z');

    $targetDir = storage_path("app/ota/{$channel}/{$runtimeVersion}/{$updateId}");
    File::ensureDirectoryExists($targetDir);

    // Extract zip archive
    $zipPath = $file->getRealPath();
    $extracted = false;

    if (class_exists(ZipArchive::class)) {
      $zip = new ZipArchive();
      if ($zip->open($zipPath) === true) {
        $zip->extractTo($targetDir);
        $zip->close();
        $extracted = true;
      }
    }

    if (!$extracted && function_exists('exec')) {
      @exec('unzip -q ' . escapeshellarg($zipPath) . ' -d ' . escapeshellarg($targetDir), $output, $code);
      if ($code === 0) {
        $extracted = true;
      }
    }

    if (!$extracted) {
      File::deleteDirectory($targetDir);
      return response()->json(['error' => 'Failed to unpack update zip archive.'], 500);
    }

    // Some zip tools wrap contents in a root "dist" folder; normalize if so
    $metadataPath = "{$targetDir}/metadata.json";
    if (!file_exists($metadataPath) && file_exists("{$targetDir}/dist/metadata.json")) {
      // Move contents of dist up one level
      File::copyDirectory("{$targetDir}/dist", $targetDir);
      File::deleteDirectory("{$targetDir}/dist");
      $metadataPath = "{$targetDir}/metadata.json";
    }

    if (!file_exists($metadataPath)) {
      File::deleteDirectory($targetDir);
      return response()->json([
        'error' => 'Invalid export package: metadata.json was not found in the archive root or dist subfolder.',
      ], 422);
    }

    $metadata = json_decode((string) file_get_contents($metadataPath), true);
    if (!is_array($metadata) || empty($metadata['fileMetadata'])) {
      File::deleteDirectory($targetDir);
      return response()->json(['error' => 'metadata.json is missing the fileMetadata section.'], 422);
    }

    $platforms = ['android', 'ios'];
    $processedPlatforms = [];

    foreach ($platforms as $platform) {
      $platformMeta = $metadata['fileMetadata'][$platform] ?? null;
      if (!$platformMeta || empty($platformMeta['bundle'])) {
        continue;
      }

      $bundleRelPath = $platformMeta['bundle'];
      $bundleAbsPath = "{$targetDir}/{$bundleRelPath}";
      if (!file_exists($bundleAbsPath)) {
        continue;
      }

      $bundleHash = $this->base64UrlSha256($bundleAbsPath);

      $manifest = [
        'id' => $updateId,
        'createdAt' => $createdAt,
        'runtimeVersion' => $runtimeVersion,
        'launchAsset' => [
          'key' => 'bundle',
          'contentType' => 'application/javascript',
          'path' => $bundleRelPath,
          'hash' => $bundleHash,
        ],
        'assets' => [],
        'metadata' => [
          'message' => $message,
          'channel' => $channel,
          'build_number' => $buildNumber,
          'min_native_build' => $minNativeBuild,
        ],
        'extra' => [
          'expoClient' => [
            'name' => 'CBH Online',
            'slug' => 'cbh-youth-online-mobile',
          ],
        ],
      ];

      if (!empty($platformMeta['assets']) && is_array($platformMeta['assets'])) {
        foreach ($platformMeta['assets'] as $asset) {
          $assetRelPath = $asset['path'] ?? null;
          if (!$assetRelPath) continue;

          $assetAbsPath = "{$targetDir}/{$assetRelPath}";
          if (!file_exists($assetAbsPath)) continue;

          $assetHash = $this->base64UrlSha256($assetAbsPath);
          $ext = ltrim((string) ($asset['ext'] ?? pathinfo($assetRelPath, PATHINFO_EXTENSION)), '.');
          $mime = $this->guessMimeType($assetRelPath);

          $manifest['assets'][] = [
            'key' => $assetHash,
            'contentType' => $mime,
            'path' => $assetRelPath,
            'fileExtension' => $ext ? ".{$ext}" : '',
            'hash' => $assetHash,
          ];
        }
      }

      file_put_contents("{$targetDir}/manifest_{$platform}.json", json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
      $processedPlatforms[] = $platform;
    }

    if (empty($processedPlatforms)) {
      File::deleteDirectory($targetDir);
      return response()->json(['error' => 'No valid bundles (android or ios) were found in metadata.json.'], 422);
    }

    // Update active release pointer
    $otaBaseDir = storage_path("app/ota/{$channel}/{$runtimeVersion}");
    $currentRecord = [
      'update_id' => $updateId,
      'created_at' => $createdAt,
      'message' => $message,
      'channel' => $channel,
      'runtime_version' => $runtimeVersion,
      'build_number' => $buildNumber,
      'min_native_build' => $minNativeBuild,
      'platforms' => $processedPlatforms,
      'rollback' => false,
    ];
    file_put_contents("{$otaBaseDir}/current.json", json_encode($currentRecord, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    // Append to history log
    $historyFile = "{$otaBaseDir}/history.json";
    $history = file_exists($historyFile) ? (json_decode((string) file_get_contents($historyFile), true) ?: []) : [];
    array_unshift($history, $currentRecord);
    if (count($history) > 20) {
      $history = array_slice($history, 0, 20);
    }
    file_put_contents($historyFile, json_encode($history, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    return response()->json([
      'success' => true,
      'update_id' => $updateId,
      'channel' => $channel,
      'runtime_version' => $runtimeVersion,
      'platforms' => $processedPlatforms,
      'message' => $message,
      'build_number' => $buildNumber,
      'min_native_build' => $minNativeBuild,
      'created_at' => $createdAt,
    ]);
  }

  /**
   * Roll back the current update to the embedded binary or to a previous update ID.
   */
  public function rollback(Request $request)
  {
    $this->authorizeOtaAdmin($request);

    $channel = $this->sanitizeChannel((string) $request->input('channel', 'production'));
    $runtimeVersion = $this->sanitizeRuntimeVersion((string) $request->input('runtime_version', '7.4.10'));
    $target = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string) $request->input('target', 'embedded')) ?: 'embedded';

    $otaBaseDir = storage_path("app/ota/{$channel}/{$runtimeVersion}");
    $currentFile = "{$otaBaseDir}/current.json";

    if (!file_exists($currentFile)) {
      return response()->json(['error' => 'No published updates found for this channel and runtime version.'], 404);
    }

    $current = json_decode((string) file_get_contents($currentFile), true) ?: [];

    if ($target === 'embedded') {
      $current['rollback'] = true;
      $current['message'] = 'Rolled back to embedded binary';
      $current['updated_at'] = now('UTC')->format('Y-m-d\TH:i:s.v\Z');
    } else {
      $targetDir = "{$otaBaseDir}/{$target}";
      if (!is_dir($targetDir)) {
        return response()->json(['error' => "Target update ID {$target} was not found."], 404);
      }
      $current['update_id'] = $target;
      $current['rollback'] = false;
      $current['message'] = "Rolled back to update {$target}";
      $current['updated_at'] = now('UTC')->format('Y-m-d\TH:i:s.v\Z');
    }

    file_put_contents($currentFile, json_encode($current, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    return response()->json([
      'success' => true,
      'target' => $target,
      'channel' => $channel,
      'runtime_version' => $runtimeVersion,
      'current' => $current,
    ]);
  }

  /**
   * List deployment history.
   */
  public function history(Request $request)
  {
    $this->authorizeOtaAdmin($request);

    $channel = $this->sanitizeChannel((string) $request->query('channel', 'production'));
    $runtimeVersion = $this->sanitizeRuntimeVersion((string) $request->query('runtime_version', '7.4.10'));

    $otaBaseDir = storage_path("app/ota/{$channel}/{$runtimeVersion}");
    $currentFile = "{$otaBaseDir}/current.json";
    $historyFile = "{$otaBaseDir}/history.json";

    $current = file_exists($currentFile) ? json_decode((string) file_get_contents($currentFile), true) : null;
    $history = file_exists($historyFile) ? json_decode((string) file_get_contents($historyFile), true) : [];

    return response()->json([
      'channel' => $channel,
      'runtime_version' => $runtimeVersion,
      'current' => $current,
      'history' => $history,
    ]);
  }

  /**
   * Helper to format an Expo Updates Protocol v1 multipart manifest response.
   */
  private function returnManifest(Request $request, array $manifest)
  {
    // If client does not speak protocol version 1 (e.g. manual curl/browser test), respond with plain JSON
    if ($request->header('expo-protocol-version') !== '1' && $request->wantsJson()) {
      return response()->json($manifest);
    }

    $crlf = "\r\n";
    $boundary = 'ExpoUpdatesBoundary' . bin2hex(random_bytes(8));
    $body = "--{$boundary}{$crlf}"
      . "Content-Type: application/json; charset=utf-8{$crlf}"
      . "Content-Disposition: form-data; name=\"manifest\"{$crlf}{$crlf}"
      . json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "{$crlf}"
      . "--{$boundary}--{$crlf}";

    return response($body, 200, [
      'Content-Type' => "multipart/mixed; boundary={$boundary}",
      'expo-protocol-version' => '1',
      'expo-sfv-version' => '0',
      'Cache-Control' => 'private, no-cache',
    ]);
  }

  /**
   * Helper to format an Expo Updates Protocol v1 multipart directive response.
   */
  private function returnDirective(Request $request, string $directiveType, array $extra = [])
  {
    $directive = array_merge(['type' => $directiveType], $extra);

    if ($request->header('expo-protocol-version') !== '1' && $request->wantsJson()) {
      return response()->json(['directive' => $directive]);
    }

    $crlf = "\r\n";
    $boundary = 'ExpoUpdatesBoundary' . bin2hex(random_bytes(8));
    $body = "--{$boundary}{$crlf}"
      . "Content-Type: application/json; charset=utf-8{$crlf}"
      . "Content-Disposition: form-data; name=\"directive\"{$crlf}{$crlf}"
      . json_encode($directive, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "{$crlf}"
      . "--{$boundary}--{$crlf}";

    return response($body, 200, [
      'Content-Type' => "multipart/mixed; boundary={$boundary}",
      'expo-protocol-version' => '1',
      'expo-sfv-version' => '0',
      'Cache-Control' => 'private, no-cache',
    ]);
  }

  /**
   * Base64URL-encoded SHA-256 hash required by Expo Updates protocol.
   */
  private function base64UrlSha256(string $filePath): string
  {
    $rawHash = hash_file('sha256', $filePath, true);
    return rtrim(strtr(base64_encode($rawHash), '+/', '-_'), '=');
  }

  /**
   * Guess MIME type for an asset path.
   */
  private function guessMimeType(string $path): string
  {
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    return match ($ext) {
      'js', 'hbc' => 'application/javascript',
      'map', 'json' => 'application/json',
      'png' => 'image/png',
      'jpg', 'jpeg' => 'image/jpeg',
      'gif' => 'image/gif',
      'webp' => 'image/webp',
      'svg' => 'image/svg+xml',
      'ttf' => 'font/ttf',
      'otf' => 'font/otf',
      'woff' => 'font/woff',
      'woff2' => 'font/woff2',
      default => 'application/octet-stream',
    };
  }

  /**
   * Authorize deployment/rollback requests via Bearer token or authenticated admin user.
   */
  private function authorizeOtaAdmin(Request $request): void
  {
    $token = $request->bearerToken() ?: $request->header('X-OTA-Token');
    $configuredToken = config('services.ota.deploy_token') ?: env('OTA_DEPLOY_TOKEN');

    if (!empty($configuredToken) && !empty($token) && hash_equals((string) $configuredToken, (string) $token)) {
      return;
    }

    $user = auth('sanctum')->user();
    if ($user && method_exists($user, 'isAdmin') && $user->isAdmin()) {
      return;
    }

    abort(401, 'Unauthorized. Provide a valid OTA_DEPLOY_TOKEN or login as an administrator.');
  }

  /**
   * Sanitize channel string to prevent directory traversal.
   */
  private function sanitizeChannel(string $channel): string
  {
    $clean = preg_replace('/[^a-zA-Z0-9_\-]/', '', $channel);
    return !empty($clean) ? $clean : 'production';
  }

  /**
   * Sanitize runtime version string to prevent directory traversal.
   */
  private function sanitizeRuntimeVersion(string $version): string
  {
    $clean = preg_replace('/[^a-zA-Z0-9_.\-]/', '', $version);
    return !empty($clean) ? $clean : '7.4.10';
  }
}
