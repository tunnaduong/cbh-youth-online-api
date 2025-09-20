<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class SettingsController extends Controller
{
    public function index()
    {
        return Inertia::render('Settings/Index');
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'setting_name' => 'required|string|max:255',
            'setting_value' => 'required|string'
        ]);

        // Update settings
        // setting([$validated['setting_name'] => $validated['setting_value']])->save();

        return redirect()->back()->with('success', 'Settings updated successfully');
    }
}
