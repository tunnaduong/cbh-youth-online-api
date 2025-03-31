<?php

namespace App\Http\Controllers;

use App\Models\AuthAccount;
use App\Models\UserContent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Intervention\Image\Facades\Image;

class UserController extends Controller
{
    // Get user avatar
    public function getAvatar($username)
    {
        try {
            // Retrieve user by username
            $user = AuthAccount::where('username', $username)->firstOrFail();

            // Retrieve the user's profile and get the profile_picture and oauth_profile_picture fields
            $userProfile = $user->profile;

            if ($userProfile) {
                // Check if profile_picture is set
                if ($userProfile->profile_picture) {
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

                // Check if oauth_profile_picture is set
                if ($userProfile->oauth_profile_picture) {
                    return redirect($userProfile->oauth_profile_picture); // Redirect to the external URL
                }
            }

            // Return the placeholder image if no avatar is found
            return response()->file(storage_path('app/public/avatars/placeholder-user.jpg'), [
                'Content-Type' => "image/jpeg", // Dynamically set the Content-Type
            ]);
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
        ]);

        // Retrieve the user by username
        $user = AuthAccount::where('username', $username)->firstOrFail();

        // Handle the file upload
        if ($request->hasFile('avatar')) {
            // Get the uploaded file
            $file = $request->file('avatar');

            // Generate a unique filename
            $fileName = time() . '_' . $file->getClientOriginalName();

            // Use Intervention Image to crop and resize to a 1:1 ratio
            $image = Image::make($file->getRealPath());
            $size = min($image->width(), $image->height()); // Get the smallest dimension
            $image->crop($size, $size)->resize(500, 500); // Crop and resize to 500x500 pixels (or any preferred size)

            // Define the file path
            $filePath = 'avatars/' . $fileName;

            // Save the cropped image to the public disk
            Storage::disk('public')->put($filePath, (string) $image->encode());

            // Update or create the user content record for the avatar
            $userContent = UserContent::create([
                'user_id' => $user->id,
                'file_name' => $fileName,
                'file_path' => $filePath,
                'file_type' => $file->getClientMimeType(),
                'file_size' => Storage::disk('public')->size($filePath),
            ]);

            // Ensure the profile relationship is loaded
            $user->load('profile');

            // Check if the profile exists
            if ($user->profile) {
                $user->profile->profile_picture = $userContent->id;
                $user->profile->save();
            } else {
                // Handle the case where the profile does not exist
                return response()->json(['message' => 'Trang cá nhân người dùng không tồn tại.'], 404);
            }

            return response()->json([
                'message' => 'Cập nhật avatar thành công.',
                'avatar' => Storage::url($filePath), // Return the file path or URL
                'user_id' => $user->id,
                'profile_picture_id' => $userContent->id,
                'profile_picture' => $user->profile->profile_picture,
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
