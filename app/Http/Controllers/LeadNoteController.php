<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\LeadNote;
use Illuminate\Http\Request;

class LeadNoteController extends Controller
{
    public function store(Request $request, Lead $lead)
    {
        $data = $request->validate([
            'note' => 'required|string',
            'note_type' => 'nullable|string|in:general,call,email,whatsapp,meeting',
            'is_private' => 'boolean',
        ]);

        $note = LeadNote::create([
            'lead_id' => $lead->id,
            'note' => $data['note'],
            'note_type' => $data['note_type'] ?? 'general',
            'is_private' => $request->boolean('is_private', false),
            'created_by' => auth()->id(),
        ]);

        $note->load('createdBy');

        return response()->json([
            'success' => true,
            'note' => [
                'id' => $note->id,
                'note' => $note->note,
                'note_type' => $note->note_type,
                'is_private' => $note->is_private,
                'created_by' => $note->createdBy?->name,
                'created_at' => $note->created_at?->format('d M Y, h:i A'),
            ],
        ]);
    }

    public function destroy(LeadNote $note)
    {
        $this->authorize('delete', $note);
        $note->delete();
        return response()->json(['success' => true]);
    }

    public function index(Lead $lead)
    {
        $notes = $lead->notes()->with('createdBy')->latest()->get();

        return response()->json([
            'notes' => $notes->map(fn($n) => [
                'id' => $n->id,
                'note' => $n->note,
                'note_type' => $n->note_type,
                'is_private' => $n->is_private,
                'created_by' => $n->createdBy?->name,
                'created_at' => $n->created_at?->format('d M Y, h:i A'),
            ]),
        ]);
    }
}
