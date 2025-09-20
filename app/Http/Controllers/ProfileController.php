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
        $user = AuthAccount::with([
            'profile',
            'posts' => function ($query) {
                $query->latest()->take(10);
            }
        ])->where('username', $username)->firstOrFail();

        return Inertia::render('Profile/Show', [
            'profile' => [
                'username' => $user->username,
                'profile_name' => $user->profile->profile_name ?? null,
                'bio' => $user->profile->bio ?? null,
                'avatar' => route('user.avatar', ['username' => $user->username]),
                'joined_at' => ucfirst($user->created_at->translatedFormat('F Y')),
                'location' => $user->profile->location ?? null,
                'posts' => $user->posts()
                    ->with('author.profile')
                    ->withCount(['views', 'comments'])
                    ->orderBy('created_at', 'desc')
                    ->get()
                    ->each(function ($post) {
                        $post->append(['image_urls', 'created_at_human']);
                    }),
                'stats' => [
                    'posts' => $user->posts()->count() ?? 0,
                    'followers' => $user->followers()->count() ?? 0,
                    'following' => $user->following()->count() ?? 0,
                    'likes' => $user->likes()->count() ?? 0,
                    'points' => $user->points() ?? 0,
                ]
            ]
        ]);
    }
}
