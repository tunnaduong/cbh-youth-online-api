<?php

namespace App\Http\Controllers;

use App\Models\UserContent;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

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
        // Fetch the user content based on the provided ID
        $content = UserContent::find($id);

        // Check if the content exists and belongs to the authenticated user
        if (!$content) {
            return response()->json(['error' => 'Content not found'], 404);
        }

        // Return the content
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
        // Validate the file input
        $request->validate([
            'file' => 'required|file|max:102400', // Adjust the max size as needed
            'uid' => 'required|integer',
        ]);

        $file = $request->file('file');
        $extension = $file->getClientOriginalExtension();
        $folder = 'others'; // Default folder

        // Determine the folder based on the file type
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
            case 'pdf':
                $folder = 'documents';
                break;
        }

        $fileName = time() . '_' . $file->getClientOriginalName();

        // Store the file in the appropriate folder
        $path = $file->storeAs($folder, $fileName, 'public');

        // Get file details
        $fileType = $file->getMimeType();
        $fileSize = $file->getSize();
        $userId = $request->uid; // Get the authenticated user's ID
        if (!$userId) {
            return response()->json(['error' => 'User not authenticated'], 401);
        }

        $data = [
            'user_id' => $userId,
            'file_name' => $fileName,
            'file_path' => $path,
            'file_type' => $fileType,
            'file_size' => $fileSize,
        ];

        $userContent = UserContent::create($data);

        // Optionally return the path or success response
        return response()->json(['message' => 'Upload ảnh thành công!', 'id' => $userContent->id, 'path' => Storage::url($path)], 201);
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
