<?php

namespace App\Http\Controllers;

use App\Models\Follower;
use App\Models\AuthAccount;
use App\Models\UserContent;
use App\Models\TopicComment;
use Illuminate\Http\Request;
use App\Models\UserSavedTopic;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Intervention\Image\Facades\Image;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Handles user-related actions such as retrieving profiles, avatars, and activity status.
 */
class UserController extends Controller
{
    /**
     * Get the avatar for a specific user.
     *
     * @param  string  $username
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse|\Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
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

    /**
     * Update the avatar for a specific user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $username
     * @return \Illuminate\Http\JsonResponse
     */
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

    private function roundToNearestFive($count)
    {
        if ($count <= 5) {
            // If count is less than or equal to 5, format it with leading zero
            return str_pad($count, 2, '0', STR_PAD_LEFT);
        } else {
            // Round down to the nearest multiple of 5 and pad to 2 digits
            return str_pad(floor($count / 5) * 5, 2, '0', STR_PAD_LEFT);
        }
    }

    /**
     * Get the profile for a specific user.
     *
     * @param  string  $username
     * @return \Illuminate\Http\JsonResponse
     */
    public function getProfile($username)
    {
        // Find the user by username
        $user = AuthAccount::where('username', $username)
            ->with(['profile', 'followers.follower', 'following.followed', 'posts.votes', 'posts.comments']) // Eager load relationships
            ->withCount(['followers', 'following', 'posts']) // Count followers, following, and posts
            ->first();

        if (!$user) {
            return response()->json(['message' => 'Không tìm thấy người dùng.'], 404);
        }

        // Calculate total likes count
        $totalLikesCount = $user->posts->sum(function ($post) {
            return $post->votes->where('vote_value', 1)->count(); // Count only upvotes
        });

        // Calculate activity points
        $activityPoints = ($user->posts_count * 10) // 10 points per post
            + ($totalLikesCount * 5) // 5 points per vote
            + ($user->posts->sum(function ($post) {
                return $post->comments->count(); // Count comments on all posts
            }) * 2); // 2 points per comment

        // Transform followers
        $followers = $user->followers->map(function ($follower) {
            $response = [
                'id' => $follower->follower->id,
                'username' => $follower->follower->username,
                'profile_name' => $follower->follower->profile->profile_name ?? null,
                'profile_picture' => "https://api.chuyenbienhoa.com/v1.0/users/{$follower->follower->username}/avatar",
            ];

            if (auth()->check()) {
                // Check if the authenticated user is following this follower
                $response['isFollowed'] = Follower::where('follower_id', auth()->id())
                    ->where('followed_id', $follower->follower->id)
                    ->exists();

                // If the follower is the current user, then isFollowed is true
                if ($follower->follower->id == auth()->id()) {
                    $response['isFollowed'] = true;
                }
            }

            return $response;
        });

        // Transform following
        $following = $user->following->map(function ($followed) {
            $response = [
                'id' => $followed->followed->id,
                'username' => $followed->followed->username,
                'profile_name' => $followed->followed->profile->profile_name ?? null,
                'profile_picture' => "https://api.chuyenbienhoa.com/v1.0/users/{$followed->followed->username}/avatar",
                'isFollowed' => false, // Default to false
            ];

            if (auth()->check()) {
                // Check if the authenticated user is following this followed user
                $isFollowing = Follower::where('follower_id', auth()->id())
                    ->where('followed_id', $followed->followed->id)
                    ->exists();

                $response['isFollowed'] = $isFollowing;
            }

            return $response;
        });

        // Transform recent posts
        $recentPosts = $user->posts()->latest()->withCount(['comments', 'views', 'votes'])->take(5)->get()->map(function ($post) {
            return [
                'id' => $post->id,
                'title' => $post->title,
                'content' => $post->description,
                'image_urls' => $post->getImageUrls()->map(function ($content) {
                    return 'https://api.chuyenbienhoa.com' . Storage::url($content->file_path);
                })->all(),
                'time' => $post->created_at->diffForHumans(),
                'comments' => $this->roundToNearestFive($post->comments_count) . "+",
                'views' => $post->views_count ?? 0,
                'votes' => $post->votes->map(function ($vote) {
                    return [
                        'username' => $vote->user->username,
                        'vote_value' => $vote->vote_value,
                        'created_at' => $vote->created_at,
                        'updated_at' => $vote->updated_at,
                    ];
                }),
                'author' => $post->anonymous ? [
                    'id' => null,
                    'username' => 'Ẩn danh',
                    'email' => null,
                    'profile_name' => 'Người dùng ẩn danh',
                    'verified' => false,
                ] : [
                    'id' => $post->author->id,
                    'username' => $post->author->username,
                    'email' => $post->author->email,
                    'profile_name' => $post->author->profile->profile_name ?? null,
                    'verified' => $post->author->profile->verified == 1 ? true : false,
                ],
                'anonymous' => $post->anonymous,
                'saved' => UserSavedTopic::where('user_id', auth()->id())->where('topic_id', $post->id)->exists(),
            ];
        });

        // Check if the user is online
        $isOnline = $user->last_activity > now()->subMinutes(5);

        // Return the user data, including the profile data, stats, followers, and following
        return response()->json([
            'id' => $user->id,
            'username' => $user->username,
            'email' => $user->email,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
            'profile' => [
                'profile_name' => $user->profile->profile_name ?? null,
                'profile_picture' => "https://api.chuyenbienhoa.com/v1.0/users/{$user->username}/avatar",
                'bio' => $user->profile->bio ?? null,
                'birthday' => $user->profile->birthday ? Carbon::parse($user->profile->birthday)->locale('vi')->format('d \T\h\á\n\g m Y') : null,
                'birthday_raw' => $user->profile->birthday ?? null,
                'gender' => $user->profile->gender ?? null,
                'location' => $user->profile->location ?? null,
                'email' => $user->email,
                'verified' => $user->profile->verified == 1 ? true : false,
                'role' => $user->role ?? null,
                'last_username_change' => $user->profile->last_username_change ?? null,
                'joined_at' => $user->created_at->translatedFormat('\T\h\á\n\g m Y'),
            ],
            'stats' => [
                'followers' => $user->followers_count,
                'following' => $user->following_count,
                'posts' => $user->posts_count,
                'total_likes_count' => $totalLikesCount,
                'activity_points' => $activityPoints,
                'is_online' => $isOnline,
                'last_activity' => $user->last_activity,
            ],
            'followers' => $followers,
            'following' => $following,
            'recent_posts' => $recentPosts,
        ]);
    }

    /**
     * Update the profile for the authenticated user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $username
     * @return \Illuminate\Http\JsonResponse
     */
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
            'birthday' => 'nullable|date',
            'gender' => 'nullable|string|in:Male,Female',
            'location' => 'nullable|string|max:255',
        ]);

        // Update user's profile
        $user->profile->update($validatedData);

        return response()->json(['message' => 'Cập nhật trang cá nhân thành công.', 'request' => $validatedData]);
    }

    /**
     * Get the online status of a specific user.
     *
     * @param  string  $username
     * @return \Illuminate\Http\JsonResponse
     */
    public function getOnlineStatus($username)
    {
        // Find the user by username
        $user = AuthAccount::where('username', $username)->first();

        if (!$user) {
            return response()->json(['message' => 'Không tìm thấy người dùng.'], 404);
        }

        // Check if the user is online
        $isOnline = $user->last_activity > now()->subMinutes(5);

        return response()->json(['is_online' => $isOnline, 'last_activity' => $user->last_activity]);
    }

    /**
     * Get the top 8 most active users based on points.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTop8ActiveUsers()
    {
        try {
            $topUsers = AuthAccount::with(['profile'])
                ->where('role', '!=', 'admin') // Exclude admin users
                ->get()
                ->map(function ($user) {
                    return [
                        'uid' => $user->id,
                        'username' => $user->username,
                        'profile_name' => $user->profile->profile_name ?? $user->username,
                        'profile_picture' => $user->profile->profile_picture ?? null,
                        'oauth_profile_picture' => $user->profile->oauth_profile_picture ?? null,
                        'total_points' => $user->points() // Use the points() method from AuthAccount model
                    ];
                })
                ->sortByDesc('total_points')
                ->take(8)
                ->values()
                ->toArray();

            return response()->json($topUsers);
        } catch (\Exception $e) {
            \Log::error('Error in getTop8ActiveUsers: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch top users: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Update the last activity timestamp for the authenticated user.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateLastActivity()
    {
        // Get the authenticated user
        $user = Auth::user();

        // Update the user's last activity
        $user->last_activity = now();
        $user->save();

        return response()->json(['message' => 'Cập nhật trạng thái hoạt động thành công.']);
    }
}
