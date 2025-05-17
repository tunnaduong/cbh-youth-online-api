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
        $user = AuthAccount::with(['profile', 'posts' => function ($query) {
            $query->latest()->take(10);
        }])->where('username', $username)->firstOrFail();

        return Inertia::render('Profile/Show', [
            'profile' => [
                'username' => $user->username,
                'profile_name' => $user->profile->profile_name ?? null,
                'bio' => $user->profile->bio ?? null,
                'avatar' => route('user.avatar', ['username' => $user->username]),
                'joined_at' => $user->created_at->format('F Y'),
                'posts' => $user->posts->map(function ($post) {
                    return [
                        'id' => $post->id,
                        'title' => $post->title,
                        'created_at' => $post->created_at->diffForHumans(),
                    ];
                })
            ]
        ]);
    }
}
