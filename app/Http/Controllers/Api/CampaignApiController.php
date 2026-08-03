<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CampaignService;
use App\Models\WhatsappCampaign;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CampaignApiController extends Controller
{
    private CampaignService $campaignService;

    public function __construct(CampaignService $campaignService)
    {
        $this->campaignService = $campaignService;
    }

    public function index(Request $request): JsonResponse
    {
        $campaigns = $this->campaignService->getAllCampaigns($request->all());

        return response()->json([
            'success' => true,
            'data' => $campaigns,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'template_id' => 'nullable|exists:whatsapp_message_templates,id',
            'type' => 'required|in:broadcast,drip,scheduled',
            'message' => 'nullable|string',
            'scheduled_at' => 'nullable|date',
            'filters' => 'nullable|array',
        ]);

        $campaign = $this->campaignService->createCampaign($validated);

        return response()->json([
            'success' => true,
            'data' => $campaign,
            'message' => 'Campaign created successfully',
        ], 201);
    }

    public function show(WhatsappCampaign $campaign): JsonResponse
    {
        $campaign->load('template', 'logs');

        return response()->json([
            'success' => true,
            'data' => $campaign,
        ]);
    }

    public function update(Request $request, WhatsappCampaign $campaign): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'template_id' => 'nullable|exists:whatsapp_message_templates,id',
            'type' => 'sometimes|required|in:broadcast,drip,scheduled',
            'message' => 'nullable|string',
            'scheduled_at' => 'nullable|date',
            'filters' => 'nullable|array',
        ]);

        $campaign = $this->campaignService->updateCampaign($campaign, $validated);

        return response()->json([
            'success' => true,
            'data' => $campaign,
            'message' => 'Campaign updated successfully',
        ]);
    }

    public function destroy(WhatsappCampaign $campaign): JsonResponse
    {
        $this->campaignService->deleteCampaign($campaign);

        return response()->json([
            'success' => true,
            'message' => 'Campaign deleted successfully',
        ]);
    }

    public function launch(WhatsappCampaign $campaign): JsonResponse
    {
        $result = $this->campaignService->launchCampaign($campaign);

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

    public function stats(WhatsappCampaign $campaign): JsonResponse
    {
        $stats = $this->campaignService->getCampaignStats($campaign);

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}
