<?php

namespace Tests\Feature;

use App\Models\AuthAccount;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use ZipArchive;

class OtaUpdateTest extends TestCase
{
  protected string $testChannel = 'testing-channel';
  protected string $testRuntime = '7.4.10';
  protected string $deployToken = 'test-ota-secret-token-12345';

  protected function setUp(): void
  {
    parent::setUp();
    config(['services.ota.deploy_token' => $this->deployToken]);

    // Clean up test OTA storage directory before each test
    $otaDir = storage_path("app/ota/{$this->testChannel}");
    if (File::isDirectory($otaDir)) {
      File::deleteDirectory($otaDir);
    }
  }

  protected function tearDown(): void
  {
    $otaDir = storage_path("app/ota/{$this->testChannel}");
    if (File::isDirectory($otaDir)) {
      File::deleteDirectory($otaDir);
    }
    parent::tearDown();
  }

  public function test_manifest_returns_no_update_available_when_no_releases_exist(): void
  {
    $response = $this->withHeaders([
      'expo-platform' => 'android',
      'expo-runtime-version' => $this->testRuntime,
      'expo-channel-name' => $this->testChannel,
      'expo-protocol-version' => '1',
    ])->get('/v1.0/ota/manifest');

    $response->assertStatus(200);
    $this->assertStringContainsString('multipart/mixed', $response->headers->get('Content-Type'));
    $this->assertStringContainsString('noUpdateAvailable', $response->getContent());
  }

  public function test_manifest_returns_json_directive_when_client_does_not_request_protocol_v1(): void
  {
    $response = $this->withHeaders([
      'Accept' => 'application/json',
      'expo-platform' => 'android',
      'expo-runtime-version' => $this->testRuntime,
      'expo-channel-name' => $this->testChannel,
    ])->get('/v1.0/ota/manifest');

    $response->assertStatus(200);
    $response->assertJson([
      'directive' => [
        'type' => 'noUpdateAvailable',
      ],
    ]);
  }

  public function test_publish_requires_authorization(): void
  {
    $response = $this->postJson('/v1.0/admin/ota/publish', [
      'channel' => $this->testChannel,
      'runtime_version' => $this->testRuntime,
    ]);

    $response->assertStatus(401);
  }

  public function test_publish_accepts_valid_zip_archive_and_registers_update(): void
  {
    $zipFile = $this->createMockExportZip();

    $response = $this->withHeaders([
      'Authorization' => "Bearer {$this->deployToken}",
    ])->post('/v1.0/admin/ota/publish', [
      'channel' => $this->testChannel,
      'runtime_version' => $this->testRuntime,
      'message' => 'Test release v1',
      'archive' => $zipFile,
    ]);

    $response->assertStatus(200);
    $response->assertJsonPath('success', true);
    $response->assertJsonPath('channel', $this->testChannel);
    $response->assertJsonPath('runtime_version', $this->testRuntime);

    $updateId = $response->json('update_id');
    $this->assertNotEmpty($updateId);

    // Verify createdAt in response has ISO 8601 UTC with milliseconds and Z (required by RCTConvert NSDate:)
    $createdAt = $response->json('created_at');
    $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z$/', $createdAt);

    // Verify files on disk
    $otaDir = storage_path("app/ota/{$this->testChannel}/{$this->testRuntime}");
    $this->assertFileExists("{$otaDir}/current.json");
    $this->assertFileExists("{$otaDir}/{$updateId}/manifest_android.json");
    $this->assertFileExists("{$otaDir}/history.json");

    $current = json_decode((string) file_get_contents("{$otaDir}/current.json"), true);
    $this->assertSame($updateId, $current['update_id']);
  }

  public function test_manifest_serves_update_manifest_after_publishing(): void
  {
    $zipFile = $this->createMockExportZip();

    $publishResponse = $this->withHeaders([
      'Authorization' => "Bearer {$this->deployToken}",
    ])->post('/v1.0/admin/ota/publish', [
      'channel' => $this->testChannel,
      'runtime_version' => $this->testRuntime,
      'message' => 'Test release v1',
      'archive' => $zipFile,
    ]);

    $updateId = $publishResponse->json('update_id');

    // 1. Request manifest as expo-updates protocol v1
    $response = $this->withHeaders([
      'expo-platform' => 'android',
      'expo-runtime-version' => $this->testRuntime,
      'expo-channel-name' => $this->testChannel,
      'expo-protocol-version' => '1',
    ])->get('/v1.0/ota/manifest');

    $response->assertStatus(200);
    $this->assertStringContainsString('multipart/mixed', $response->headers->get('Content-Type'));
    $this->assertStringContainsString($updateId, $response->getContent());
    $this->assertStringContainsString("v1.0/ota/assets/{$this->testChannel}/{$this->testRuntime}/{$updateId}/", $response->getContent());

    // 2. Request manifest when client already has this update_id
    $upToDateResponse = $this->withHeaders([
      'expo-platform' => 'android',
      'expo-runtime-version' => $this->testRuntime,
      'expo-channel-name' => $this->testChannel,
      'expo-current-update-id' => $updateId,
      'expo-protocol-version' => '1',
    ])->get('/v1.0/ota/manifest');

    $upToDateResponse->assertStatus(200);
    $this->assertStringContainsString('noUpdateAvailable', $upToDateResponse->getContent());
  }

  public function test_rollback_returns_roll_back_to_embedded_directive(): void
  {
    $zipFile = $this->createMockExportZip();

    $this->withHeaders([
      'Authorization' => "Bearer {$this->deployToken}",
    ])->post('/v1.0/admin/ota/publish', [
      'channel' => $this->testChannel,
      'runtime_version' => $this->testRuntime,
      'archive' => $zipFile,
    ]);

    // Request rollback to embedded
    $rollbackResponse = $this->withHeaders([
      'Authorization' => "Bearer {$this->deployToken}",
    ])->post('/v1.0/admin/ota/rollback', [
      'channel' => $this->testChannel,
      'runtime_version' => $this->testRuntime,
      'target' => 'embedded',
    ]);

    $rollbackResponse->assertStatus(200);
    $rollbackResponse->assertJsonPath('success', true);

    // Now manifest check should return rollBackToEmbedded directive
    $response = $this->withHeaders([
      'expo-platform' => 'android',
      'expo-runtime-version' => $this->testRuntime,
      'expo-channel-name' => $this->testChannel,
      'expo-protocol-version' => '1',
    ])->get('/v1.0/ota/manifest');

    $response->assertStatus(200);
    $this->assertStringContainsString('rollBackToEmbedded', $response->getContent());
  }

  public function test_asset_streaming_and_path_traversal_prevention(): void
  {
    $zipFile = $this->createMockExportZip();

    $publishResponse = $this->withHeaders([
      'Authorization' => "Bearer {$this->deployToken}",
    ])->post('/v1.0/admin/ota/publish', [
      'channel' => $this->testChannel,
      'runtime_version' => $this->testRuntime,
      'archive' => $zipFile,
    ]);

    $updateId = $publishResponse->json('update_id');

    // 1. Valid asset request
    $assetResponse = $this->get("/v1.0/ota/assets/{$this->testChannel}/{$this->testRuntime}/{$updateId}/bundle.js");
    $assetResponse->assertStatus(200);
    $this->assertStringContainsString('immutable', $assetResponse->headers->get('Cache-Control'));
    $this->assertSame('console.log("hello world");', file_get_contents($assetResponse->getFile()->getPathname()));

    // 2. Directory traversal attempt
    $attackResponse = $this->get("/v1.0/ota/assets/{$this->testChannel}/{$this->testRuntime}/{$updateId}/../../../../etc/passwd");
    $attackResponse->assertStatus(404);
  }

  /**
   * Helper to build a mock exported Expo dist zip archive.
   */
  protected function createMockExportZip(): UploadedFile
  {
    $tempZipPath = tempnam(sys_get_temp_dir(), 'ota_zip_') . '.zip';
    $zip = new ZipArchive();
    $zip->open($tempZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

    $metadata = [
      'version' => 0,
      'bundler' => 'metro',
      'fileMetadata' => [
        'android' => [
          'bundle' => 'bundle.js',
          'assets' => [
            [
              'path' => 'assets/icon.png',
              'ext' => 'png',
            ],
          ],
        ],
      ],
    ];

    $zip->addFromString('metadata.json', json_encode($metadata));
    $zip->addFromString('bundle.js', 'console.log("hello world");');
    $zip->addFromString('assets/icon.png', 'fake-image-bytes');
    $zip->close();

    return new UploadedFile($tempZipPath, 'dist.zip', 'application/zip', null, true);
  }
}
