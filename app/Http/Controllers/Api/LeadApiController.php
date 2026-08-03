<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LeadService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Lead;

class LeadApiController extends Controller
{
    private LeadService $leadService;

    public function __construct(LeadService $leadService)
    {
        $this->leadService = $leadService;
    }

    public function index(Request $request): JsonResponse
    {
        $leads = $this->leadService->getAllLeads($request->all(), $request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $leads,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'whatsapp' => 'nullable|string|max:20',
            'source' => 'nullable|string|max:100',
            'status' => 'nullable|in:open,in_progress,won,lost',
            'lead_source_id' => 'nullable|exists:lead_sources,id',
            'lead_stage_id' => 'nullable|exists:stages,id',
            'assigned_user_id' => 'nullable|exists:users,id',
            'notes' => 'nullable|string',
            'tags' => 'nullable|array',
            'tags.*' => 'exists:lead_tags,id',
        ]);

        $lead = $this->leadService->createLead($validated);

        return response()->json([
            'success' => true,
            'data' => $lead,
            'message' => 'Lead created successfully',
        ], 201);
    }

    public function show(Lead $lead): JsonResponse
    {
        $lead->load(['leadSource', 'stage', 'assignedUser', 'tags', 'notes.createdBy', 'whatsappConversation']);

        return response()->json([
            'success' => true,
            'data' => $lead,
        ]);
    }

    public function update(Request $request, Lead $lead): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'whatsapp' => 'nullable|string|max:20',
            'source' => 'nullable|string|max:100',
            'status' => 'nullable|in:open,in_progress,won,lost',
            'lead_source_id' => 'nullable|exists:lead_sources,id',
            'lead_stage_id' => 'nullable|exists:stages,id',
            'assigned_user_id' => 'nullable|exists:users,id',
            'notes' => 'nullable|string',
            'tags' => 'nullable|array',
            'tags.*' => 'exists:lead_tags,id',
        ]);

        $lead = $this->leadService->updateLead($lead, $validated);

        return response()->json([
            'success' => true,
            'data' => $lead,
            'message' => 'Lead updated successfully',
        ]);
    }

    public function destroy(Lead $lead): JsonResponse
    {
        $this->leadService->deleteLead($lead);

        return response()->json([
            'success' => true,
            'message' => 'Lead deleted successfully',
        ]);
    }

    public function addNote(Request $request, Lead $lead): JsonResponse
    {
        $validated = $request->validate([
            'note' => 'required|string',
            'note_type' => 'nullable|string|max:50',
            'is_private' => 'nullable|boolean',
        ]);

        $note = $this->leadService->addNote(
            $lead,
            $validated['note'],
            $validated['note_type'] ?? 'general',
            $validated['is_private'] ?? false
        );

        return response()->json([
            'success' => true,
            'data' => $note,
            'message' => 'Note added successfully',
        ], 201);
    }

    public function convertToCustomer(Lead $lead): JsonResponse
    {
        $result = $this->leadService->convertToCustomer($lead);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], 400);
        }

        return response()->json([
            'success' => true,
            'data' => $result,
            'message' => $result['message'],
        ]);
    }

    public function stats(): JsonResponse
    {
        $stats = $this->leadService->getLeadStats();

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    public function byStage(): JsonResponse
    {
        $stages = $this->leadService->getLeadsByStage();

        return response()->json([
            'success' => true,
            'data' => $stages,
        ]);
    }
}
