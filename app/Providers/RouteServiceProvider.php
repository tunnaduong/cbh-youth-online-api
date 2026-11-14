<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

/**
 * The application's route service provider.
 *
 * This provider is responsible for configuring the application's routes,
 * including rate limiting and loading the route files.
 */
class RouteServiceProvider extends ServiceProvider
{
  /**
   * The path to your application's "home" route.
   *
   * Typically, users are redirected here after authentication.
   *
   * @var string
   */
  public const HOME = '/';

  /**
   * Define your route model bindings, pattern filters, and other route configuration.
   *
   * @return void
   */
  public function boot(): void
  {
    RateLimiter::for('api', function (Request $request) {
      return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
    });

    // WebRTC call signaling (SDP offer/answer + trickle ICE candidates) bursts
    // 30-60 requests within a few seconds per call; give it a dedicated high limit.
    RateLimiter::for('calls', function (Request $request) {
      return Limit::perMinute(1000)->by('calls:' . ($request->user()?->id ?: $request->ip()));
    });

    // One game load is dozens of file requests; key by ticket so a busy
    // household on one IP is not throttled together.
    RateLimiter::for('game-play', function (Request $request) {
      return Limit::perMinute(1500)->by('game-play:' . ($request->route('ticket') ?: $request->ip()));
    });

    // Each AI quiz generation call burns AI credit across the shared
    // GEMINI_1..GEMINI_5 keys - cap it at 1 per user per minute so nobody
    // can spam /quiz/start and drain them.
    RateLimiter::for('quiz-generate', function (Request $request) {
      return Limit::perMinute(1)->by($request->user()?->id ?: $request->ip());
    });

    // In-app feedback is open to guests, so keep bots from flooding the
    // admin inbox: a few per minute, a couple dozen per day.
    RateLimiter::for('feedback', function (Request $request) {
      $key = $request->user()?->id ?: $request->ip();
      return [Limit::perMinute(3)->by('feedback-min:' . $key), Limit::perDay(20)->by('feedback-day:' . $key)];
    });

    $this->routes(function () {
      Route::middleware('api')
        ->group(base_path('routes/api.php'));

      Route::middleware('web')
        ->group(base_path('routes/web.php'));
    });
  }
}
