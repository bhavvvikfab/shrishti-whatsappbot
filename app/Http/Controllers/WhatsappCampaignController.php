<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\WhatsappCampaign;
use App\Models\WhatsappMessageTemplate;
use App\Services\WhatsAppInboxService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;

class WhatsappCampaignController extends Controller
{
    public function __construct(private WhatsAppInboxService $inboxService) {}

    public function index()
    {
        $campaigns = WhatsappCampaign::with('template')
            ->orderByDesc('created_at')
            ->paginate(20);

        $stats = [
            'total' => WhatsappCampaign::count(),
            'draft' => WhatsappCampaign::where('status', 'draft')->count(),
            'scheduled' => WhatsappCampaign::where('status', 'scheduled')->count(),
            'completed' => WhatsappCampaign::where('status', 'completed')->count(),
        ];

        return view('whatsapp.campaigns.index', compact('campaigns', 'stats'));
    }

    public function create()
    {
        $templates = WhatsappMessageTemplate::where('is_active', true)
            ->whereRaw("UPPER(TRIM(status)) = 'APPROVED'")
            ->orderBy('name')
            ->get();

        $totalLeads = Lead::whereNotNull('whatsapp')->where('whatsapp', '!=', '')->count();

        return view('whatsapp.campaigns.create', compact('templates', 'totalLeads'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'template_id' => 'nullable|exists:whatsapp_message_templates,id',
            'type' => 'required|in:broadcast,drip,scheduled',
            'message' => 'nullable|string',
            'scheduled_at' => 'nullable|date|after:now',
            'filters' => 'nullable|array',
        ]);

        $data['status'] = $request->scheduled_at ? 'scheduled' : 'draft';
        $data['created_by'] = auth()->id();

        $campaign = WhatsappCampaign::create($data);

        return redirect()->route('whatsapp.campaigns.show', $campaign)
            ->with('success', 'Campaign created successfully.');
    }

    public function show(WhatsappCampaign $campaign)
    {
        $campaign->load('template');

        return view('whatsapp.campaigns.show', compact('campaign'));
    }

    public function edit(WhatsappCampaign $campaign)
    {
        if (!in_array($campaign->status, ['draft', 'scheduled'])) {
            return redirect()->route('whatsapp.campaigns.show', $campaign)
                ->with('error', 'Cannot edit a campaign that is already running or completed.');
        }

        $templates = WhatsappMessageTemplate::where('is_active', true)
            ->whereRaw("UPPER(TRIM(status)) = 'APPROVED'")
            ->orderBy('name')
            ->get();

        return view('whatsapp.campaigns.edit', compact('campaign', 'templates'));
    }

    public function update(Request $request, WhatsappCampaign $campaign)
    {
        if (!in_array($campaign->status, ['draft', 'scheduled'])) {
            return response()->json(['success' => false, 'message' => 'Cannot edit this campaign.'], 422);
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'template_id' => 'nullable|exists:whatsapp_message_templates,id',
            'type' => 'required|in:broadcast,drip,scheduled',
            'message' => 'nullable|string',
            'scheduled_at' => 'nullable|date',
        ]);

        $data['updated_by'] = auth()->id();
        $campaign->update($data);

        return redirect()->route('whatsapp.campaigns.show', $campaign)
            ->with('success', 'Campaign updated successfully.');
    }

    public function destroy(WhatsappCampaign $campaign)
    {
        $campaign->delete();
        return response()->json(['success' => true]);
    }

    /**
     * Launch a campaign immediately.
     */
    public function launch(WhatsappCampaign $campaign)
    {
        if (!in_array($campaign->status, ['draft', 'scheduled'])) {
            return response()->json(['success' => false, 'message' => 'Campaign cannot be launched.'], 422);
        }

        // Dispatch as a queued job for large campaigns
        dispatch(function () use ($campaign) {
            app(WhatsAppInboxService::class)->sendCampaign($campaign);
        })->afterResponse();

        return response()->json(['success' => true, 'message' => 'Campaign launched successfully.']);
    }

    /**
     * Cancel a campaign.
     */
    public function cancel(WhatsappCampaign $campaign)
    {
        if (!in_array($campaign->status, ['draft', 'scheduled', 'sending'])) {
            return response()->json(['success' => false, 'message' => 'Campaign cannot be cancelled.'], 422);
        }

        $campaign->update(['status' => 'cancelled']);

        return response()->json(['success' => true]);
    }
}
