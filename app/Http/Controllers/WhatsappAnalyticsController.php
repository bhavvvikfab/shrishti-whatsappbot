<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\User;
use App\Models\WhatsappCampaign;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WhatsappAnalyticsController extends Controller
{
    public function dashboard()
    {
        $stats = $this->getStats();
        $leadsBySource = $this->getLeadsBySource();
        $leadsByStage = $this->getLeadsByStage();
        $dailyMessages = $this->getDailyMessages();
        $topAgents = $this->getTopAgents();
        $recentCampaigns = WhatsappCampaign::with('template')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        return view('whatsapp.analytics.dashboard', compact(
            'stats',
            'leadsBySource',
            'leadsByStage',
            'dailyMessages',
            'topAgents',
            'recentCampaigns'
        ));
    }

    public function getChartData(Request $request)
    {
        $type = $request->type ?? 'messages';
        $period = $request->period ?? '7days';

        $days = match ($period) {
            '30days' => 30,
            '90days' => 90,
            default => 7,
        };

        $startDate = now()->subDays($days);

        if ($type === 'messages') {
            $data = WhatsappMessage::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN direction = 'incoming' THEN 1 ELSE 0 END) as incoming"),
                DB::raw("SUM(CASE WHEN direction = 'outgoing' THEN 1 ELSE 0 END) as outgoing")
            )
                ->where('created_at', '>=', $startDate)
                ->groupBy('date')
                ->orderBy('date')
                ->get();
        } elseif ($type === 'leads') {
            $data = Lead::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN is_converted = 1 THEN 1 ELSE 0 END) as converted")
            )
                ->where('created_at', '>=', $startDate)
                ->groupBy('date')
                ->orderBy('date')
                ->get();
        } else {
            $data = collect();
        }

        return response()->json(['data' => $data]);
    }

    private function getStats(): array
    {
        return [
            'total_leads' => Lead::count(),
            'new_leads_today' => Lead::whereDate('created_at', today())->count(),
            'converted_leads' => Lead::where('is_converted', true)->count(),
            'total_conversations' => WhatsappConversation::count(),
            'open_conversations' => WhatsappConversation::where('status', 'open')->count(),
            'unread_messages' => WhatsappConversation::sum('unread_count'),
            'messages_today' => WhatsappMessage::whereDate('created_at', today())->count(),
            'total_campaigns' => WhatsappCampaign::count(),
            'active_campaigns' => WhatsappCampaign::whereIn('status', ['scheduled', 'sending'])->count(),
            'messages_sent_total' => WhatsappMessage::where('direction', 'outgoing')->count(),
            'messages_received_total' => WhatsappMessage::where('direction', 'incoming')->count(),
            'conversion_rate' => Lead::count() > 0
                ? round((Lead::where('is_converted', true)->count() / Lead::count()) * 100, 1)
                : 0,
        ];
    }

    private function getLeadsBySource(): array
    {
        return Lead::select('lead_source_id', DB::raw('COUNT(*) as total'))
            ->with('leadSource')
            ->groupBy('lead_source_id')
            ->get()
            ->map(fn($item) => [
                'source' => $item->leadSource?->name ?? 'Unknown',
                'total' => $item->total,
            ])
            ->toArray();
    }

    private function getLeadsByStage(): array
    {
        return Lead::select('lead_stage_id', DB::raw('COUNT(*) as total'))
            ->with('stage')
            ->groupBy('lead_stage_id')
            ->get()
            ->map(fn($item) => [
                'stage' => $item->stage?->name ?? 'No Stage',
                'total' => $item->total,
            ])
            ->toArray();
    }

    private function getDailyMessages(): array
    {
        return WhatsappMessage::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('COUNT(*) as total')
        )
            ->where('created_at', '>=', now()->subDays(7))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->toArray();
    }

    private function getTopAgents(): array
    {
        return WhatsappConversation::select('assigned_user_id', DB::raw('COUNT(*) as total'))
            ->with('assignedUser')
            ->whereNotNull('assigned_user_id')
            ->groupBy('assigned_user_id')
            ->orderByDesc('total')
            ->limit(5)
            ->get()
            ->map(fn($item) => [
                'agent' => $item->assignedUser?->name ?? 'Unknown',
                'total' => $item->total,
            ])
            ->toArray();
    }
}
