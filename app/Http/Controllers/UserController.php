<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\AuthAccount;
use App\Models\UserContent;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class UserController extends Controller
{
    // Get user avatar
    public function getAvatar($username)
    {
        try {
            // Retrieve user by username
            $user = AuthAccount::where('username', $username)->firstOrFail();

            // Retrieve the user's profile and get the profile_picture field
            $userProfile = $user->profile;

            if ($userProfile && $userProfile->profile_picture) {
                // Retrieve the content using profile_picture (which holds the id in cyo_cdn_user_content)
                $userContent = UserContent::find($userProfile->profile_picture);

                if ($userContent) {
                    // Get the full path to the image
                    $imagePath = storage_path('app/public/' . $userContent->file_path);

                    // Check if the file exists
                    if (file_exists($imagePath)) {
                        return response()->file($imagePath, [
                            'Content-Type' => $userContent->file_type, // Dynamically set the Content-Type
                        ]);
                    }

                    return response()->json(['message' => 'Ảnh không tồn tại.'], 404);
                }

                return response()->json(['message' => 'Không tìm thấy avatar nào cho người dùng này.'], 404);
            }

            return response()->json(['message' => 'Trang cá nhân người dùng hoặc avatar người dùng không tồn tại.'], 404);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Không tìm thấy người dùng.'], 404);
        }
    }


    // Update user avatar
    public function updateAvatar(Request $request, $username)
    {
        // Validate the incoming request
        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,jpg,gif|max:10240', // Validate the file type and size
            'username' => 'required|string|exists:cyo_auth_accounts,username', // Validate the username
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
            $userContent = UserContent::create([
                'user_id' => $user->id,
                'file_name' => $fileName,
                'file_path' => $filePath,
                'file_type' => $file->getClientMimeType(),
                'file_size' => $file->getSize(),
            ]);

            $user->profile->profile_picture = $userContent->id;
            $user->profile->save();

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
            return response()->json(['message' => 'Không tìm thấy người dùng.'], 404);
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
            return response()->json(['message' => 'Truy cập trái phép.'], 403);
        }

        // Validate incoming profile data
        $validatedData = $request->validate([
            'profile_name' => 'nullable|string|max:255',
            'profile_picture' => 'nullable|integer', // Optional, if updating avatar via URL
            'bio' => 'nullable|string',
            'birthday' => 'nullable|string',
            'gender' => 'nullable|string',
            'location' => 'nullable|string|max:255',
        ]);

        // Update user's profile
        $user->profile->update($validatedData);

        return response()->json(['message' => 'Cập nhật trang cá nhân thành công.']);
    }
}
