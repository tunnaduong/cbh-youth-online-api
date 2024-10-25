<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class FileUploadController extends Controller
{
    // Method to show the upload form
    public function showForm()
    {
        return view('upload');
    }

    // Method to handle file upload
    public function upload(Request $request)
    {
        // Validate the file input
        $request->validate([
            'file' => 'required|file|max:2048', // Adjust the max size as needed
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

        // Store the file in the appropriate folder
        $path = $file->storeAs($folder, time() . '_' . $file->getClientOriginalName(), 'public');

        // Optionally return the path or success response
        return response()->json(['path' => Storage::url($path)], 201);
    }
}
