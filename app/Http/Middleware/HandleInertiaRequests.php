<?php

namespace App\Http\Middleware;

use App\Http\Controllers\StoryController;
use App\Models\ForumMainCategory;
use Illuminate\Http\Request;
use Inertia\Middleware;

/**
 * Handle Inertia requests and share default data with all views.
 */
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
   *
   * @param  \Illuminate\Http\Request  $request
   * @return string|null
   */
  public function version(Request $request): string|null
  {
    return parent::version($request);
  }

  /**
   * Define the props that are shared by default.
   *
   * @param  \Illuminate\Http\Request  $request
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
          ForumMainCategory::with('subForums')->orderBy('arrange', 'asc')->get() :
          ForumMainCategory::with('subForums')
            ->where('role_restriction', '!=', 'admin')
            ->orderBy('arrange', 'asc')
            ->get(),
      ],
      'stories' => $this->getStories($request),
      'is_logged_in' => $request->user() ? true : false,
      'flash' => [
        'success' => fn() => $request->session()->get('success'),
        'error' => fn() => $request->session()->get('error'),
      ],
    ];
  }

  /**
   * Get stories data for Inertia using the StoryController.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return mixed
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
