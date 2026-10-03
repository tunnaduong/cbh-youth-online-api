<?php

namespace App\Http\Controllers;

use App\Services\ProfileThemeService;

/**
 * Name fonts hosted by the API (public/fonts/name). Clients fetch the list
 * once and load a font file only when a name using it is on screen, so new
 * fonts can be added here without shipping a new web or app build.
 */
class NameFontController extends Controller
{
  /**
   * Every server-hosted name font: key (the `name_font` value), display
   * label, the family name to register it under, and where to download it.
   *
   * @return \Illuminate\Http\JsonResponse
   */
  public function index()
  {
    return response()
      ->json(['fonts' => ProfileThemeService::serverFonts()])
      ->header('Cache-Control', 'public, max-age=3600');
  }

  /**
   * One font file. Only files named in the registry are served.
   *
   * @param  string  $file
   * @return \Symfony\Component\HttpFoundation\Response
   */
  public function show(string $file)
  {
    $known = array_column(ProfileThemeService::SERVER_FONTS, 'file');
    $path = public_path('fonts/name/' . $file);

    if (!in_array($file, $known, true) || !is_file($path)) {
      abort(404);
    }

    return response()->file($path, [
      'Content-Type' => 'font/ttf',
      // The file for a key never changes; a new version gets a new key.
      'Cache-Control' => 'public, max-age=31536000, immutable',
    ]);
  }
}
