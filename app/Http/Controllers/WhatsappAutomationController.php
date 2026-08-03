<?php

namespace App\Http\Controllers;

use App\Models\WhatsappAutomationRule;
use App\Models\WhatsappMessageTemplate;
use Illuminate\Http\Request;

class WhatsappAutomationController extends Controller
{
    public function index()
    {
        $rules = WhatsappAutomationRule::with('template')
            ->orderByDesc('priority')
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('whatsapp.automation.index', compact('rules'));
    }

    public function create()
    {
        $templates = WhatsappMessageTemplate::where('is_active', true)
            ->whereRaw("UPPER(TRIM(status)) = 'APPROVED'")
            ->orderBy('name')
            ->get();

        return view('whatsapp.automation.create', compact('templates'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'trigger_type' => 'required|in:keyword,welcome,faq,drip,followup,scheduled',
            'trigger_keywords' => 'nullable|string',
            'template_id' => 'nullable|exists:whatsapp_message_templates,id',
            'response_message' => 'nullable|string',
            'is_active' => 'boolean',
            'priority' => 'integer|min:0|max:100',
            'conditions' => 'nullable|array',
        ]);

        $data['is_active'] = $request->boolean('is_active', true);
        $data['created_by'] = auth()->id();

        WhatsappAutomationRule::create($data);

        return redirect()->route('whatsapp.automation.index')
            ->with('success', 'Automation rule created successfully.');
    }

    public function edit(WhatsappAutomationRule $rule)
    {
        $templates = WhatsappMessageTemplate::where('is_active', true)
            ->whereRaw("UPPER(TRIM(status)) = 'APPROVED'")
            ->orderBy('name')
            ->get();

        return view('whatsapp.automation.edit', compact('rule', 'templates'));
    }

    public function update(Request $request, WhatsappAutomationRule $rule)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'trigger_type' => 'required|in:keyword,welcome,faq,drip,followup,scheduled',
            'trigger_keywords' => 'nullable|string',
            'template_id' => 'nullable|exists:whatsapp_message_templates,id',
            'response_message' => 'nullable|string',
            'is_active' => 'boolean',
            'priority' => 'integer|min:0|max:100',
        ]);

        $data['is_active'] = $request->boolean('is_active', true);
        $data['updated_by'] = auth()->id();

        $rule->update($data);

        return redirect()->route('whatsapp.automation.index')
            ->with('success', 'Automation rule updated successfully.');
    }

    public function destroy(WhatsappAutomationRule $rule)
    {
        $rule->delete();
        return response()->json(['success' => true]);
    }

    public function toggleStatus(WhatsappAutomationRule $rule)
    {
        $rule->update(['is_active' => !$rule->is_active]);
        return response()->json(['success' => true, 'is_active' => $rule->is_active]);
    }
}
