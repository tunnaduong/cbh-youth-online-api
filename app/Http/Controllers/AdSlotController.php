<?php

namespace App\Http\Controllers;

use App\Models\AdSlot;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdSlotController extends Controller
{
  /** Public: live creatives, optionally for one placement. */
  public function index(Request $request)
  {
    $query = AdSlot::live()->orderByDesc('priority')->orderByDesc('id');
    $placement = $request->query('placement');
    if ($placement) {
      $query->where('placement', $placement);
    }
    $response = response()->json(
      $query->get(['id', 'name', 'placement', 'image_url', 'link_url', 'alt_text', 'html_code', 'priority'])
    );
    if ($placement && AdSlot::where('placement', $placement)->exists()) {
      $response->header('X-Placement-Managed', '1');
    }
    return $response;
  }

  public function track(Request $request, int $id)
  {
    $type = $request->input('type') === 'click' ? 'clicks' : 'impressions';
    AdSlot::whereKey($id)->increment($type);
    return response()->json(['ok' => true]);
  }

  public function adminIndex()
  {
    return response()->json([
      'data' => AdSlot::orderBy('placement')->orderByDesc('priority')->orderByDesc('id')->get(),
      'placements' => AdSlot::PLACEMENTS,
    ]);
  }

  public function adminSave(Request $request, ?int $id = null)
  {
    $data = $request->validate([
      'name' => 'required|string|max:255',
      'placement' => ['required', Rule::in(AdSlot::PLACEMENTS)],
      'image_url' => 'nullable|required_without:html_code|string|max:500',
      'link_url' => 'nullable|string|max:500',
      'alt_text' => 'nullable|string|max:255',
      'html_code' => 'nullable|required_without:image_url|string|max:20000',
      'is_active' => 'boolean',
      'priority' => 'nullable|integer',
      'starts_at' => 'nullable|date',
      'ends_at' => 'nullable|date|after_or_equal:starts_at',
    ]);
    $data['priority'] = $data['priority'] ?? 0;
    if (!empty($data['html_code'])) {
      $data['image_url'] = $data['image_url'] ?? null;
    }

    $ad = $id ? AdSlot::findOrFail($id) : new AdSlot();
    $ad->fill($data)->save();
    return response()->json($ad);
  }

  public function adminDestroy(int $id)
  {
    AdSlot::findOrFail($id)->delete();
    return response()->json(['ok' => true]);
  }
}
