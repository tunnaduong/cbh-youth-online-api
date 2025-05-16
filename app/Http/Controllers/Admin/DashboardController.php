<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Topic;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'users_count' => DB::table('cyo_auth_accounts')->count(),
            'topics_count' => DB::table('cyo_topics')->count(),
            'classes_count' => DB::table('cyo_school_classes')->count(),
            'reports_count' => DB::table('cyo_volunteer_daily_reports')->count(),
        ];

        return Inertia::render('Admin/Dashboard', [
            'stats' => $stats
        ]);
    }
}
