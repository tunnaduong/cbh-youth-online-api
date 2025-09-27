<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\AuthAccount;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => session('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }

    public function show($username)
    {
        return $this->renderProfile($username, 'posts');
    }

    public function showWithTab($username, $tab)
    {
        return $this->renderProfile($username, $tab);
    }

    private function renderProfile($username, $tab = 'posts')
    {
        // Find user (database collation is case-insensitive)
        $user = AuthAccount::with([
            'profile',
            'posts' => function ($query) {
                $query->latest()->take(10);
            }
        ])->where('username', $username)->first();

        // If user found but username doesn't match exactly, redirect to correct username
        if ($user && $user->username !== $username) {
            $redirectUrl = $tab === 'posts'
                ? route('profile.show', ['username' => $user->username])
                : route('profile.show.tab', ['username' => $user->username, 'tab' => $tab]);

            return redirect($redirectUrl, 301); // 301 permanent redirect
        }

        // If still no user found, throw 404
        if (!$user) {
            abort(404);
        }

        // Check if current user is following this profile
        $isFollowing = false;
        if (auth()->check()) {
            $isFollowing = \App\Models\Follower::where('follower_id', auth()->id())
                ->where('followed_id', $user->id)
                ->exists();
        }

        return Inertia::render('Profile/Show', [
            'profile' => [
                'username' => $user->username,
                'profile_name' => $user->profile->profile_name ?? null,
                'bio' => $user->profile->bio ?? null,
                'avatar' => route('user.avatar', ['username' => $user->username]),
                'joined_at' => ucfirst($user->created_at->translatedFormat('F Y')),
                'location' => $user->profile->location ?? null,
                'posts' => $this->getUserPosts($user, $username),
                'verified' => $user->profile->verified ?? 0,
                'stats' => [
                    'posts' => $user->posts()->where('anonymous', false)->count() ?? 0,
                    'followers' => $user->followers()->count() ?? 0,
                    'following' => $user->following()->count() ?? 0,
                    'likes' => $user->posts()->where('anonymous', false)->withCount([
                        'votes' => function ($query) {
                            $query->where('vote_value', 1);
                        }
                    ])->get()->sum('votes_count') ?? 0,
                    'points' => $user->points() ?? 0,
                ],
                'followers' => $user->followers()->with('follower.profile')->get()->map(function ($follower) {
                    $follower->follower->isFollowing = auth()->check() ?
                        \App\Models\Follower::where('follower_id', auth()->id())
                            ->where('followed_id', $follower->follower->id)
                            ->exists() : false;
                    return $follower;
                }),
                'following' => $user->following()->with('followed.profile')->get()->map(function ($following) {
                    $following->followed->isFollowing = auth()->check() ?
                        \App\Models\Follower::where('follower_id', auth()->id())
                            ->where('followed_id', $following->followed->id)
                            ->exists() : false;
                    return $following;
                }),
                'isFollowing' => $isFollowing,
            ],
            'activeTab' => $tab,
        ]);
    }

    private function getUserPosts($user, $username)
    {
        $query = $user->posts()
            ->with('author.profile')
            ->withCount(['views', 'comments'])
            ->withSum('votes', 'vote_value')
            ->orderBy('created_at', 'desc');

        // Apply privacy filtering
        if (auth()->check()) {
            $userId = auth()->id();
            $isOwnProfile = $user->id === $userId;

            if ($isOwnProfile) {
                // User can see all their own posts regardless of privacy or hidden status. No extra filters needed.
            } else {
                // For other users, hide anonymous posts
                $query->where('anonymous', false);

                // Check if current user is following this profile
                $isFollowing = \App\Models\Follower::where('follower_id', $userId)
                    ->where('followed_id', $user->id)
                    ->exists();

                if ($isFollowing) {
                    $query->where(function ($q) {
                        $q->where('privacy', 'public')
                            ->orWhere('privacy', 'followers');
                    });
                } else {
                    // Only public posts
                    $query->where('privacy', 'public');
                }
            }
        } else {
            // For non-authenticated users, hide anonymous posts and only show public
            $query->where('anonymous', false)
                ->where('privacy', 'public');
        }

        return $query->get()->each(function ($post) {
            $post->append(['image_urls', 'created_at_human']);
        });
    }
}
