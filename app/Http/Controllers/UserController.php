<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\AuthAccount;
use App\Models\UserContent;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    // Get user avatar
    public function getAvatar($username)
    {
        // Retrieve user by username
        $user = User::where('username', $username)->firstOrFail();

        // Retrieve user content associated with the user
        $userContent = UserContent::where('user_id', $user->id)->first();

        if ($userContent) {
            // Get the full path to the image
            $imagePath = storage_path('app/public/' . $userContent->file_path);

            // Check if the file exists
            if (file_exists($imagePath)) {
                return response()->file($imagePath, [
                    'Content-Type' => 'image/png', // Adjust according to the image type
                ]);
            }

            return response()->json(['message' => 'Image not found.'], 404);
        }

        return response()->json(['message' => 'No content found for this user.'], 404);
    }

    // Update user avatar
    public function updateAvatar(Request $request, $username)
    {
        // Get the authenticated user
        $user2 = Auth::user();

        // Check if the authenticated user's username matches the provided username
        if ($user2->username !== $username) {
            return response()->json(['message' => 'Bạn không thể đổi avatar của người khác.'], 403);
        }

        // Validate the incoming request
        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,jpg,gif|max:10240', // Validate the file type and size
        ]);

        // Retrieve the user by username
        $user = AuthAccount::where('username', $username)->firstOrFail();
        // Handle the file upload
        if ($request->hasFile('avatar')) {
            // Get the uploaded file
            $file = $request->file('avatar');

            // Generate a unique filename
            $fileName = time() . '_' . $file->getClientOriginalName();

            // Store the file in the public disk (or any other desired location)
            $filePath = $file->storeAs('avatars', $fileName, 'public');

            // Update or create the user content record for the avatar
            $userContent = UserContent::updateOrCreate(
                ['user_id' => $user->id], // Condition to find the record
                [
                    'file_name' => $fileName,
                    'file_path' => $filePath,
                    'file_type' => $file->getClientMimeType(),
                    'file_size' => $file->getSize(),
                ]
            );

            $user2->profile_picture = $userContent->id;
            /** @var \App\Models\UserProfile $user2 **/
            $user2->save();

            return response()->json([
                'message' => 'Cập nhật avatar thành công.',
                'avatar' => Storage::url($filePath), // Return the file path or URL
            ], 200);
        }

        return response()->json(['message' => 'Không có file nào được upload.'], 400);
    }

    // Get user profile by username
    public function getProfile($username)
    {
        // Find the user by username
        $user = AuthAccount::where('username', $username)->with('profile')->first();

        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        // Return the user data, including the profile data
        return response()->json([
            'id' => $user->id,
            'username' => $user->username,
            'email' => $user->email,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
            'profile' => [
                'profile_name' => $user->profile->profile_name ?? null,
                'profile_picture' => $user->profile->profile_picture ?? null,
                'bio' => $user->profile->bio ?? null,
                'birthday' => $user->profile->birthday ?? null,
                'gender' => $user->profile->gender ?? null,
                'location' => $user->profile->location ?? null,
            ],
        ]);
    }

    // Update user profile by username (only for the logged-in user)
    public function updateProfile(Request $request, $username)
    {
        // Get the authenticated user
        $user = Auth::user();

        // Check if the logged-in user's username matches the requested username
        if ($user->username !== $username) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        // Validate incoming profile data
        $validatedData = $request->validate([
            'profile_name' => 'nullable|string|max:255',
            'bio' => 'nullable|string',
            'location' => 'nullable|string|max:255',
            'avatar_url' => 'nullable|url', // Optional, if updating avatar via URL
        ]);

        // Update user's profile
        $user->profile->update($validatedData);

        return response()->json(['message' => 'Profile updated successfully.']);
    }
}
