<?php

namespace App\Http\Controllers;

use App\Models\UserContent;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class FileUploadController extends Controller
{
    // Method to handle file upload
    public function upload(Request $request)
    {
        Log::info($request->all());

        // Validate the file input
        $request->validate([
            'file' => 'required|file|max:20480', // Adjust the max size as needed
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

        UserContent::create($data);

        // Optionally return the path or success response
        return response()->json(['path' => Storage::url($path)], 201);
    }

    public function destroy($id)
    {
        $content = UserContent::findOrFail($id);
        $content->delete();

        return response()->json(['message' => 'Content deleted successfully.'], Response::HTTP_OK);
    }
}
