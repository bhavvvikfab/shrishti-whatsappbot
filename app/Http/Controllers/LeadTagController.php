<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\LeadTag;
use Illuminate\Http\Request;

class LeadTagController extends Controller
{
    public function index()
    {
        $tags = LeadTag::withCount('leads')->orderBy('name')->get();
        return view('whatsapp.lead-tags.index', compact('tags'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100|unique:lead_tags,name',
            'color' => 'nullable|string|max:20',
            'description' => 'nullable|string|max:500',
        ]);

        $data['created_by'] = auth()->id();
        $tag = LeadTag::create($data);

        return response()->json(['success' => true, 'tag' => $tag]);
    }

    public function update(Request $request, LeadTag $tag)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100|unique:lead_tags,name,' . $tag->id,
            'color' => 'nullable|string|max:20',
            'description' => 'nullable|string|max:500',
            'is_active' => 'boolean',
        ]);

        $tag->update($data);

        return response()->json(['success' => true, 'tag' => $tag]);
    }

    public function destroy(LeadTag $tag)
    {
        $tag->delete();
        return response()->json(['success' => true]);
    }

    /**
     * Attach tags to a lead.
     */
    public function attachToLead(Request $request, Lead $lead)
    {
        $request->validate(['tag_ids' => 'required|array', 'tag_ids.*' => 'exists:lead_tags,id']);

        foreach ($request->tag_ids as $tagId) {
            $lead->tags()->syncWithoutDetaching([$tagId => ['created_by' => auth()->id()]]);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Detach a tag from a lead.
     */
    public function detachFromLead(Request $request, Lead $lead, LeadTag $tag)
    {
        $lead->tags()->detach($tag->id);
        return response()->json(['success' => true]);
    }

    /**
     * Get all active tags (for dropdowns).
     */
    public function list()
    {
        $tags = LeadTag::active()->orderBy('name')->get(['id', 'name', 'color']);
        return response()->json(['tags' => $tags]);
    }
}
