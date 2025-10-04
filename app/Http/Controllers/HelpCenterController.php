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
    public function index(): Response
    {
        return Inertia::render('HelpCenter/Index');
    }

    /**
     * Show a specific help article.
     *
     * @param string $category
     * @param string $article
     * @return Response
     */
    public function show(string $category, string $article): Response
    {
        return Inertia::render('HelpCenter/Show', [
            'categorySlug' => $category,
            'articleSlug' => $article,
        ]);
    }

    /**
     * Display the About page.
     */
    public function about(): Response
    {
        return Inertia::render('HelpCenter/Static/About');
    }

    /**
     * Display the Jobs page.
     */
    public function jobs(): Response
    {
        return Inertia::render('HelpCenter/Static/Jobs');
    }

    /**
     * Display the Ads page.
     */
    public function ads(): Response
    {
        return Inertia::render('HelpCenter/Static/Ads');
    }

    /**
     * Display the Contact page.
     */
    public function contact(): Response
    {
        return Inertia::render('HelpCenter/Static/Contact');
    }
}