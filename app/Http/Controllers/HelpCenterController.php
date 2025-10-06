<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HelpCenterController extends Controller
{
  /**
   * Display the main help center page.
   */
  public function index(): \Illuminate\Http\JsonResponse
  {
    return response()->json(['message' => 'Help center index data']);
  }

  /**
   * Show a specific help article.
   *
   * @param string $category
   * @param string $article
   * @return \Illuminate\Http\JsonResponse
   */
  public function show(string $category, string $article): \Illuminate\Http\JsonResponse
  {
    return response()->json([
      'categorySlug' => $category,
      'articleSlug' => $article,
    ]);
  }

  /**
   * Display the About page.
   */
  public function about(): \Illuminate\Http\JsonResponse
  {
    return response()->json(['message' => 'About page data']);
  }

  /**
   * Display the Jobs page.
   */
  public function jobs(): \Illuminate\Http\JsonResponse
  {
    return response()->json(['message' => 'Jobs page data']);
  }

  /**
   * Display the Ads page.
   */
  public function ads(): \Illuminate\Http\JsonResponse
  {
    return response()->json(['message' => 'Ads page data']);
  }

  /**
   * Display the Contact page.
   */
  public function contact(): \Illuminate\Http\JsonResponse
  {
    return response()->json(['message' => 'Contact page data']);
  }
}
