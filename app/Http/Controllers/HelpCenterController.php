<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class HelpCenterController extends Controller
{
  public function index()
  {
    return Inertia::render('HelpCenter/Index');
  }

  public function search(Request $request)
  {
    $query = $request->input('query');
    // TODO: Implement help center search
    return Inertia::render('HelpCenter/Search', [
      'query' => $query,
      'results' => []
    ]);
  }

  public function show($id)
  {
    // TODO: Implement fetching help article by ID
    return Inertia::render('HelpCenter/Show', [
      'article' => [
        'id' => $id,
        'title' => '',
        'content' => ''
      ]
    ]);
  }
}
