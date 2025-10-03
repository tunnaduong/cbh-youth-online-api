<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\AuthAccount;
use App\Models\UserProfile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * Handles user settings, including profile information and account deletion.
 */
class SettingsController extends Controller
{
    /**
     * Display the user settings page.
     *
     * @return \Inertia\Response
     */
    public function index()
    {
        $user = Auth::user();
        $user->load('profile');

        return Inertia::render('Settings/Index', [
            'user' => $user
        ]);
    }

    /**
     * Update the user's profile and notification settings.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'username' => 'required|string|max:255|unique:cyo_auth_accounts,username,' . $user->id,
            'gender' => 'required|in:male,female',
            'location' => 'nullable|string|max:255',
            'bio' => 'nullable|string|max:500',
            'full_name' => 'nullable|string|max:255',
            'date_of_birth' => 'nullable',
            'language' => 'required|in:vi,en',
            // Notification settings
            'notification_level' => 'nullable|in:all,mentions,none',
            'email_contact' => 'nullable|boolean',
            'email_marketing' => 'nullable|boolean',
            'email_social' => 'nullable|boolean',
            'email_security' => 'nullable|boolean',
        ]);

        // Update user profile
        $profile = $user->profile;
        if (!$profile) {
            $profile = new UserProfile();
            $profile->user_id = $user->id;
        }

        $profile->gender = $validated['gender'];
        $profile->location = $validated['location'];
        $profile->bio = $validated['bio'];
        $profile->profile_name = $validated['full_name'];

        // Handle date_of_birth - convert from dayjs object to proper date format
        if ($validated['date_of_birth']) {
            $profile->birthday = $validated['date_of_birth'];
        }

        $profile->language = $validated['language'];

        // Update notification settings
        if (isset($validated['notification_level'])) {
            $profile->notification_level = $validated['notification_level'];
        }
        if (isset($validated['email_contact'])) {
            $profile->email_contact = $validated['email_contact'];
        }
        if (isset($validated['email_marketing'])) {
            $profile->email_marketing = $validated['email_marketing'];
        }
        if (isset($validated['email_social'])) {
            $profile->email_social = $validated['email_social'];
        }
        if (isset($validated['email_security'])) {
            $profile->email_security = $validated['email_security'];
        }

        $profile->save();

        // Update username if changed
        if ($user->username !== $validated['username']) {
            $user->username = $validated['username'];
            $user->save();
        }

        return redirect()->back()->with('success', 'Hồ sơ đã được cập nhật thành công!');
    }

    /**
     * Delete the user's account.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function deleteAccount(Request $request)
    {
        \Log::info('Delete account request received', [
            'user_id' => Auth::id(),
            'request_data' => $request->all()
        ]);

        $user = Auth::user();

        try {
            $validated = $request->validate([
                'password' => 'required|string',
                'confirm_text' => 'required|string|in:XÓA TÀI KHOẢN',
            ]);

            \Log::info('Validation passed', ['validated' => $validated]);

            // Verify password
            if (!Hash::check($validated['password'], $user->password)) {
                \Log::warning('Password verification failed');
                return redirect()->back()->withErrors(['password' => 'Mật khẩu không chính xác.']);
            }

            \Log::info('Password verified, proceeding with account deletion');

            // Delete user and related data
            $user->delete();

            // Logout user
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            \Log::info('Account deleted successfully');
            return redirect()->route('login')->with('success', 'Tài khoản đã được xóa thành công.');
        } catch (\Exception $e) {
            \Log::error('Error deleting account', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return redirect()->back()->withErrors(['error' => 'Có lỗi xảy ra khi xóa tài khoản.']);
        }
    }
}
