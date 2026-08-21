<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessImageCompression;
use App\Jobs\ProcessVideoCompression;
use App\Models\UserContent;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Handles the uploading, showing, and deleting of user-generated content.
 */
class FileUploadController extends Controller
{
  /**
   * Display the specified resource.
   *
   * @param  int  $id
   * @return \Illuminate\Http\JsonResponse
   */
  public function show($id)
  {
    $content = UserContent::find($id);

    if (!$content) {
      return response()->json(['error' => 'Content not found'], 404);
    }

    return response()->json($content, 200);
  }

  /**
   * Handle the upload of a file.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function upload(Request $request)
  {
    $request->validate([
      'file' => 'required|file|max:102400',
      'uid' => 'required|integer',
    ], [
      'file.max' => 'Tệp quá lớn. Kích thước tối đa là 100MB.',
    ]);

    $file = $request->file('file');
    $extension = strtolower($file->getClientOriginalExtension());
    $folder = 'others';

    switch ($extension) {
      case 'jpg':
      case 'jpeg':
      case 'png':
      case 'gif':
        $folder = 'images';
        break;

      case 'mp4':
      case 'avi':
      case 'mov':
      case 'mkv':
      case 'webm':
        $folder = 'videos';
        break;

      case 'mp3':
      case 'wav':
      case 'ogg':
        $folder = 'sounds';
        break;

      case 'txt':
      case 'doc':
      case 'docx':
      case 'xls':
      case 'xlsx':
      case 'pdf':
        $folder = 'documents';
        break;
    }

    $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
    $fileName = time() . '_' . Str::slug($originalName) . '.' . $extension;

    $path = $file->storeAs($folder, $fileName, 'public');

    $userId = $request->uid;
    if (!$userId) {
      return response()->json(['error' => 'User not authenticated'], 401);
    }

    $isVideo = $folder === 'videos';
    $isImage = $folder === 'images';

    $data = [
      'user_id'      => $userId,
      'file_name'    => $fileName,
      'file_path'    => $path,
      'file_type'    => $file->getMimeType(),
      'file_size'    => $file->getSize(),
      'video_status' => $isVideo ? 'pending' : null,
    ];

    // Same small (480px) preview generation chat uses for messages (see
    // MediaThumbnailService) - a post/story card never needs to decode a
    // full-resolution original just to render a feed-sized thumbnail.
    // Image thumbnails and the video first-frame are cheap enough to
    // generate synchronously (same as chat); the muted low-bitrate video
    // preview clip used for feed autoplay is a real ffmpeg transcode, so it
    // stays a queued job like ProcessVideoCompression already is.
    if ($isImage) {
      $data['thumbnail_path'] = \App\Services\MediaThumbnailService::imageThumbnail($path);
    } elseif ($isVideo) {
      $data['thumbnail_path'] = \App\Services\MediaThumbnailService::videoFirstFrame($path);
    }

    $userContent = UserContent::create($data);

    if ($isVideo) {
      ProcessVideoCompression::dispatch($path, $userContent->id);
      \App\Jobs\ProcessUserContentVideoPreview::dispatch($path, $userContent->id);

      // Refresh to get updated size/status after job runs (sync queue runs inline)
      $userContent->refresh();

      return response()->json([
        'message'      => $userContent->video_status === 'completed'
          ? 'Upload video thành công!'
          : 'Upload video thành công! Video đang được xử lý.',
        'id'           => $userContent->id,
        'path'         => Storage::url($userContent->file_path),
        'thumbnail_url' => $userContent->thumbnail_url,
        'preview_url'  => $userContent->preview_url,
        'video_status' => $userContent->video_status,
      ], 201);
    }

    if ($isImage) {
      ProcessImageCompression::dispatch($path, $userContent->id);

      $userContent->refresh();
    }

    return response()->json([
      'message' => 'Upload ảnh thành công!',
      'id'      => $userContent->id,
      'path'    => Storage::url($userContent->file_path),
      'thumbnail_url' => $userContent->thumbnail_url,
    ], 201);
  }

  /**
   * Remove the specified resource from storage.
   *
   * @param  int  $id
   * @return \Illuminate\Http\JsonResponse
   */
  public function destroy($id)
  {
    $content = UserContent::findOrFail($id);
    $content->delete();

    return response()->json(['message' => 'Content deleted successfully.'], Response::HTTP_OK);
  }
}
