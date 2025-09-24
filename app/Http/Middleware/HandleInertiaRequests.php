<?php

namespace App\Http\Middleware;

use App\Http\Controllers\StoryController;
use App\Models\ForumMainCategory;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): string|null
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
                'profile' => $request->user() ? $request->user()->profile : null,
            ],
            'forum_data' => [
                'main_categories' => $request->user()?->role == 'admin' ?
                    ForumMainCategory::with('subForums')->get() :
                    ForumMainCategory::with('subForums')
                        ->where('role_restriction', '!=', 'admin')
                        ->orderBy('arrange', 'asc')
                        ->get(),
            ],
            'stories' => $this->getStories($request),
            'is_logged_in' => $request->user() ? true : false,
        ];
    }

    /**
     * Get stories data for Inertia using StoryController
     */
    private function getStories(Request $request)
    {
        // Use StoryController's index method
        $storyController = new StoryController();
        $response = $storyController->index($request);

        // Extract the stories data from the response
        $storiesData = $response->getData(true);

        return $storiesData;
    }
}
