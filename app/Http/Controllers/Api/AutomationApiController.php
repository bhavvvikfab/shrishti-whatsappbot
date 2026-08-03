<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WhatsAppAutomationService;
use App\Models\WhatsappAutomationRule;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AutomationApiController extends Controller
{
    private WhatsAppAutomationService $automationService;

    public function __construct(WhatsAppAutomationService $automationService)
    {
        $this->automationService = $automationService;
    }

    public function index(): JsonResponse
    {
        $rules = $this->automationService->getActiveRules();

        return response()->json([
            'success' => true,
            'data' => $rules,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'trigger_type' => 'required|in:keyword,welcome,faq,drip,followup,scheduled',
            'trigger_keywords' => 'nullable|string',
            'template_id' => 'nullable|exists:whatsapp_message_templates,id',
            'response_message' => 'nullable|string',
            'is_active' => 'boolean',
            'priority' => 'integer',
            'conditions' => 'nullable|array',
        ]);

        $rule = $this->automationService->createAutomationRule($validated);

        return response()->json([
            'success' => true,
            'data' => $rule,
            'message' => 'Automation rule created successfully',
        ], 201);
    }

    public function show(WhatsappAutomationRule $rule): JsonResponse
    {
        $rule->load('template');

        return response()->json([
            'success' => true,
            'data' => $rule,
        ]);
    }

    public function update(Request $request, WhatsappAutomationRule $rule): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'trigger_type' => 'sometimes|required|in:keyword,welcome,faq,drip,followup,scheduled',
            'trigger_keywords' => 'nullable|string',
            'template_id' => 'nullable|exists:whatsapp_message_templates,id',
            'response_message' => 'nullable|string',
            'is_active' => 'boolean',
            'priority' => 'integer',
            'conditions' => 'nullable|array',
        ]);

        $rule = $this->automationService->updateAutomationRule($rule, $validated);

        return response()->json([
            'success' => true,
            'data' => $rule,
            'message' => 'Automation rule updated successfully',
        ]);
    }

    public function destroy(WhatsappAutomationRule $rule): JsonResponse
    {
        $this->automationService->deleteAutomationRule($rule);

        return response()->json([
            'success' => true,
            'message' => 'Automation rule deleted successfully',
        ]);
    }

    public function toggle(WhatsappAutomationRule $rule): JsonResponse
    {
        $rule->update(['is_active' => !$rule->is_active]);

        return response()->json([
            'success' => true,
            'data' => $rule,
            'message' => 'Automation rule status toggled',
        ]);
    }
}
