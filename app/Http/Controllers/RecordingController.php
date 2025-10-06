<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Recording;

/**
 * Handles the display and management of audio recordings.
 */
class RecordingController extends Controller
{
  /**
   * Display a listing of recordings.
   *
   * @return \Inertia\Response
   */
  public function index()
  {
    $recordings = Recording::with([
      'author.profile',
      'cdnAudio',
      'cdnPreview',
    ])
      ->withCount('views')
      ->latest()
      ->get()
      ->map(function ($recording) {
        $recording->created_at_human = $recording->created_at->diffForHumans();
        return $recording;
      });

    return response()->json([
      'recordings' => $recordings
    ]);
  }

  /**
   * Display the specified recording.
   *
   * @param  \App\Models\Recording  $recording
   * @return \Inertia\Response
   */
  public function show(Recording $recording)
  {
    return response()->json([
      'recording' => $recording
    ]);
  }

  /**
   * Store a newly created recording in storage.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\RedirectResponse
   */
  public function store(Request $request)
  {
    $validated = $request->validate([
      'title' => 'required|string|max:255',
      'description' => 'required|string',
      'likes' => 'required|integer',
      'views' => 'required|integer',
      'duration' => 'required|string'
    ]);

    Recording::create($validated);

    return redirect()->route('recordings.index')
      ->with('success', 'Recording created successfully.');
  }

  /**
   * Remove the specified recording.
   *
   * @param  \App\Models\Recording  $recording
   * @return \Illuminate\Http\RedirectResponse
   */
  public function destroy(Recording $recording)
  {
    $recording->delete();

    return redirect()->route('recordings.index')
      ->with('success', 'Recording deleted successfully.');
  }

  /**
   * Show the form for creating a new recording.
   *
   * @return \Inertia\Response
   */
  public function create()
  {
    return response()->json(['message' => 'Create recording form data']);
  }
}
