<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\WhatsappConfig;
use App\Models\WhatsappLog;
use App\Models\WhatsappMessageTemplate;
use App\Services\WhatsappConfigResolver;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class WhatsappConfigController extends Controller
{
    public function __construct(private WhatsappConfigResolver $configResolver) {}

    public function settingsPage()
    {
        $user = Auth::user();
        abort_unless($user && ($user->isAdmin() || $user->hasMatrixPermission('view_whatsapp')), 403);

        $whatsappModuleEnabled = Setting::isEnabled('whatsapp_module_enabled', true);
        $mode = $this->configResolver->resolveMode($user);
        $canEdit = $user->isAdmin() || $mode !== WhatsappConfigResolver::MODE_SHARED;
        $usingShared = $mode === WhatsappConfigResolver::MODE_SHARED;

        return view('whatsapp.settings', compact('whatsappModuleEnabled', 'mode', 'canEdit', 'usingShared'));
    }

    private function whatsappApiClient(bool $verifySsl = true)
    {
        $client = Http::timeout(20)
            ->withOptions([
                'proxy' => '',
                'curl' => [
                    CURLOPT_PROXY => '',
                ],
            ])
            ->retry(1, 250);

        if (! $verifySsl || app()->environment(['local', 'development'])) {
            $client = $client->withoutVerifying();
        }

        return $client;
    }

    private function whatsappApiRequest(string $businessAccountId, string $accessToken)
    {
        try {
            return $this->whatsappApiClient()->get("https://graph.facebook.com/v19.0/{$businessAccountId}/message_templates", [
                'access_token' => $accessToken,
                'limit' => 100,
            ]);
        } catch (\Throwable $e) {
            if (! str_contains(strtolower($e->getMessage()), 'ssl certificate')) {
                throw $e;
            }

            return $this->whatsappApiClient(false)->get("https://graph.facebook.com/v19.0/{$businessAccountId}/message_templates", [
                'access_token' => $accessToken,
                'limit' => 100,
            ]);
        }
    }

    public function show()
    {
        $user = Auth::user();
        abort_unless($user, 403);

        $activeConfig = WhatsappConfig::forUser($user);
        $ownConfig = $this->configResolver->ownConfigForUser($user);
        $mode = $this->configResolver->resolveMode($user);

        $data = $ownConfig ? $ownConfig->toArray() : ($activeConfig ? $activeConfig->toArray() : []);

        if (empty($data['webhook_url'])) {
            $data['webhook_url'] = WhatsappConfig::webhookCallbackUrl();
        }

        if (empty($data['verify_token'])) {
            $data['verify_token'] = config('services.whatsapp.verify_token', '');
        }

        $data['whatsapp_config_mode'] = $mode;
        $data['can_edit'] = $user->isAdmin() || $mode !== WhatsappConfigResolver::MODE_SHARED;
        $data['using_shared_config'] = $mode === WhatsappConfigResolver::MODE_SHARED;
        $data['has_own_config'] = $ownConfig !== null;

        return response()->json($data);
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        abort_unless($user, 403);

        if (! $user->isAdmin() && $this->configResolver->resolveMode($user) === WhatsappConfigResolver::MODE_SHARED) {
            return response()->json([
                'message' => 'You are using the admin WhatsApp bot. Ask admin to remove shared access if you need your own bot.',
            ], 403);
        }

        $userId = $user->id;
        $config = $this->configResolver->ownConfigForUser($user);

        $rules = [
            'app_id' => 'required|string',
            'phone_number_id' => 'required|string',
            'business_account_id' => 'required|string',
            'access_token' => 'required|string',
            'webhook_url' => 'nullable|string',
            'verify_token' => 'nullable|string',
        ];

        if (! $config || ! $config->app_secret) {
            $rules['app_secret'] = 'required|string';
        } else {
            $rules['app_secret'] = 'nullable|string';
        }

        $data = $request->validate($rules);

        if (empty($data['webhook_url'])) {
            $data['webhook_url'] = WhatsappConfig::webhookCallbackUrl();
        }

        $data['user_id'] = $userId;

        if (! $config) {
            $data['created_by'] = $userId;
            $data['modified_by'] = $userId;
            $config = WhatsappConfig::create($data);
        } else {
            if (! empty($data['app_secret'])) {
                $config->app_secret = $data['app_secret'];
            }

            $config->fill(collect($data)->except('app_secret')->toArray());
            $config->modified_by = $userId;
            $config->save();
        }

        if (! $user->isAdmin()) {
            $this->configResolver->revokeSharedAccess($user);
        }

        $subscription = $this->ensureWabaAppSubscription($config);

        return response()->json([
            'status' => 'ok',
            'waba_subscribed' => $subscription['success'],
            'waba_subscribe_message' => $subscription['message'],
            'whatsapp_config_mode' => WhatsappConfigResolver::MODE_OWN,
        ]);
    }

    /**
     * @return array{success: bool, message: string}
     */
    private function ensureWabaAppSubscription(WhatsappConfig $config): array
    {
        if (! filled($config->business_account_id) || ! filled($config->access_token)) {
            return [
                'success' => false,
                'message' => 'Business Account ID or Access Token missing; skipped WABA subscription.',
            ];
        }

        try {
            $response = $this->whatsappApiClient()
                ->withToken($config->access_token)
                ->post("https://graph.facebook.com/v19.0/{$config->business_account_id}/subscribed_apps");

            if ($response->successful() && ($response->json('success') === true || $response->status() === 200)) {
                return [
                    'success' => true,
                    'message' => 'App subscribed to WhatsApp Business Account for inbound messages.',
                ];
            }

            return [
                'success' => false,
                'message' => 'Failed to subscribe app to WABA: '.($response->body() ?: 'unknown error'),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Failed to subscribe app to WABA: '.$e->getMessage(),
            ];
        }
    }

    public function refreshTemplates()
    {
        $user = Auth::user();
        abort_unless($user && $user->isAdmin(), 403);

        $config = WhatsappConfig::forUser($user);

        if (! $config || ! $config->access_token || ! $config->business_account_id) {
            return response()->json([
                'message' => 'WhatsApp configuration is incomplete. Please save App ID, Business Account ID and Access Token first.',
            ], 422);
        }

        try {
            $response = $this->whatsappApiRequest($config->business_account_id, $config->access_token);
        } catch (\Throwable $e) {
            if (app()->environment(['local', 'development'])) {
                return response()->json([
                    'message' => 'WhatsApp API could not be reached from local. Showing cached templates from database.',
                    'warning' => true,
                    'templates' => WhatsappMessageTemplate::visibleForSettings()->orderBy('name')->get(),
                ]);
            }

            return response()->json([
                'message' => 'Failed to contact WhatsApp API.',
            ], 500);
        }

        if (! $response->successful()) {
            if (app()->environment(['local', 'development'])) {
                return response()->json([
                    'message' => 'WhatsApp API responded with an error in local. Showing cached templates from database.',
                    'warning' => true,
                    'details' => $response->json(),
                    'templates' => WhatsappMessageTemplate::visibleForSettings()->orderBy('name')->get(),
                ]);
            }

            return response()->json([
                'message' => 'WhatsApp API error.',
                'details' => $response->json(),
            ], $response->status());
        }

        $data = $response->json('data') ?? [];

        foreach ($data as $tpl) {
            $template = WhatsappMessageTemplate::firstOrNew([
                'name' => $tpl['name'] ?? '',
            ]);

            $template->language = $tpl['language'] ?? $template->language;
            $template->status = $tpl['status'] ?? $template->status;
            $template->category = $tpl['category'] ?? $template->category;
            $template->components_json = $tpl['components'] ?? $template->components_json;

            $template->save();
        }

        $templates = WhatsappMessageTemplate::visibleForSettings()->orderBy('name')->get();

        return response()->json([
            'message' => 'Templates refreshed successfully.',
            'templates' => $templates,
        ]);
    }

    public function updateTemplateModule(WhatsappMessageTemplate $template, Request $request)
    {
        abort_unless(Auth::user()?->isAdmin(), 403);

        $data = $request->validate([
            'use_for_module' => 'nullable|string|max:255',
        ]);

        $template->use_for_module = $data['use_for_module'] ?? null;
        $template->save();

        return response()->json(['status' => 'ok']);
    }

    public function updateTemplateStatus(WhatsappMessageTemplate $template, Request $request)
    {
        abort_unless(Auth::user()?->isAdmin(), 403);

        $data = $request->validate([
            'is_active' => 'required|boolean',
        ]);

        $template->is_active = (bool) $data['is_active'];
        $template->save();

        return response()->json(['status' => 'ok']);
    }

    public function send(Request $request)
    {
        $data = $request->validate([
            'to_number' => 'required|string',
            'template_name' => 'required|string',
            'variables' => 'nullable|array',
            'module' => 'nullable|string',
            'module_id' => 'nullable|integer',
        ]);

        $service = app(WhatsAppService::class)->useConfig(WhatsappConfig::forUser(Auth::user()));

        if (! $service->isConfigured()) {
            return response()->json(['success' => false, 'message' => 'WhatsApp is not configured.'], 422);
        }

        $sent = $service->sendTemplate(
            $data['to_number'],
            $data['template_name'],
            $data['variables'] ?? [],
            $data['module'] ?? null,
            $data['module_id'] ?? null
        );

        return response()->json([
            'success' => $sent,
            'message' => $sent ? 'Message sent successfully.' : 'Failed to send message.',
        ], $sent ? 200 : 500);
    }

    public function logs(Request $request)
    {
        abort_unless(Auth::user()?->isAdmin(), 403);

        $logs = WhatsappLog::with('sender')
            ->when($request->module, fn ($query) => $query->where('module', $request->module))
            ->latest()
            ->paginate(20);

        return response()->json($logs);
    }
}
