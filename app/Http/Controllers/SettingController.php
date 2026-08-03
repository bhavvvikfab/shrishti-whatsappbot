<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\WhatsappMessageTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class SettingController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (!auth()->user()?->isAdmin()) {
                abort(403, 'Unauthorized access to settings.');
            }
            return $next($request);
        });
    }

    public function index()
    {
        $settings = Setting::all()->keyBy('key');
        $whatsappTemplates = WhatsappMessageTemplate::visibleForSettings()->orderBy('name')->get();
        $whatsappModuleOptions = $this->whatsappModuleOptions();
        $whatsappModuleEnabled = Setting::isEnabled('whatsapp_module_enabled', true);
        $whatsappAutoAiEnabled = Setting::isEnabled('whatsapp_auto_ai_enabled', true);

        return view('settings.index', compact('settings', 'whatsappTemplates', 'whatsappModuleOptions', 'whatsappModuleEnabled', 'whatsappAutoAiEnabled'));
    }

    public function update(Request $request)
    {
        try {
            $this->persistSettings($request);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (Throwable $e) {
            Log::error('Settings update failed', [
                'route' => 'settings.update',
                'inputs' => array_keys($request->except(['_token', '_method'])),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return back()->with('error', 'Settings save failed. Check logs for details.');
        }

        return back()->with('success', 'Settings saved successfully!');
    }

    public function apiIndex(): JsonResponse
    {
        $settings = Setting::query()
            ->whereIn('key', [
                'mail_host',
                'mail_port',
                'mail_username',
                'mail_password',
                'mail_encryption',
                'mail_from_name',
                'email_notifications_admin',
                'email_notifications_staff',
                'email_notifications_customer',
                'whatsapp_module_enabled',
                'whatsapp_auto_ai_enabled',
                'whatsapp_notifications_admin',
                'whatsapp_notifications_staff',
                'whatsapp_notifications_customer',
                'google_client_id',
                'google_client_secret',
                'google_redirect_uri',
                'firebase_key_file',
            ])
            ->pluck('value', 'key');

        return response()->json([
            'success' => true,
            'data' => [
                'settings' => $settings,
                'whatsapp_module_options' => $this->whatsappModuleOptions(),
            ],
        ]);
    }

    public function apiUpdate(Request $request): JsonResponse
    {
        try {
            $this->persistSettings($request);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Throwable $e) {
            Log::error('Settings API update failed', [
                'route' => 'api.settings.update',
                'inputs' => array_keys($request->except(['_token', '_method'])),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => config('app.debug')
                    ? $e->getMessage()
                    : 'Settings save failed on server.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Settings saved successfully.',
        ]);
    }

    private function persistSettings(Request $request): void
    {
        $validated = $request->validate([
            'mail_host' => ['sometimes', 'nullable', 'string', 'max:255'],
            'mail_port' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:65535'],
            'mail_username' => ['sometimes', 'nullable', 'string', 'max:255'],
            'mail_password' => ['sometimes', 'nullable', 'string', 'max:255'],
            'mail_encryption' => ['sometimes', 'nullable', 'in:tls,ssl,'],
            'mail_from_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'email_notifications_admin' => ['sometimes', 'nullable', 'in:0,1'],
            'email_notifications_staff' => ['sometimes', 'nullable', 'in:0,1'],
            'email_notifications_customer' => ['sometimes', 'nullable', 'in:0,1'],
            'whatsapp_module_enabled' => ['sometimes', 'nullable', 'in:0,1'],
            'whatsapp_auto_ai_enabled' => ['sometimes', 'nullable', 'in:0,1'],
            'whatsapp_notifications_admin' => ['sometimes', 'nullable', 'in:0,1'],
            'whatsapp_notifications_staff' => ['sometimes', 'nullable', 'in:0,1'],
            'whatsapp_notifications_customer' => ['sometimes', 'nullable', 'in:0,1'],
            'google_client_id' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'google_client_secret' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'google_redirect_uri' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'facebook_app_id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'facebook_app_secret' => ['sometimes', 'nullable', 'string', 'max:255'],
            'facebook_verify_token' => ['sometimes', 'nullable', 'string', 'max:255'],
            'firebase_key_file' => ['sometimes', 'nullable', 'file', 'mimes:json', 'max:2048'],
        ]);

        foreach ($validated as $key => $value) {
            if ($request->hasFile($key)) {
                $oldSetting = Setting::where('key', $key)->first();
                if ($oldSetting && $oldSetting->value && Storage::disk('public')->exists($oldSetting->value)) {
                    Storage::disk('public')->delete($oldSetting->value);
                }

                $path = $request->file($key)->store('company', 'public');
                $value = $path;
            } elseif ($request->exists($key) && $request->file($key) === null && str_contains($key, 'logo')) {
                continue;
            }

            $attributes = ['value' => $this->normalizeSettingValue($value)];

            // Some live databases may still be on an older schema; avoid writing
            // columns that do not exist there.
            if (Schema::hasColumn('settings', 'group')) {
                $attributes['group'] = 'general';
            }

            if (Schema::hasColumn('settings', 'type')) {
                $attributes['type'] = $request->hasFile($key) ? 'file' : 'string';
            }

            Setting::updateOrCreate(['key' => $key], $attributes);
        }
    }

    private function normalizeSettingValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE);
    }

    private function whatsappModuleOptions(): array
    {
        return [
            'hello_world' => 'Hello World',
            'meeting_scheduled_customer' => 'Meeting Scheduled Customer',
            'meeting_scheduled_staff' => 'Meeting Scheduled Staff',
            'meeting_updated' => 'Meeting Updated',
            'staff_account_created' => 'Staff Account Created',
            'staff_account_updated' => 'Staff Account Updated',
            'task_assigned_staff' => 'Task Assigned Staff',
            'task_created_customer' => 'Task Created Customer',
            'task_updated_customer' => 'Task Updated Customer',
            'task_updated_staff' => 'Task Updated Staff',
            'customer_welcome_message' => 'Customer Welcome Message',
            'customer_profile_updated' => 'Customer Profile Updated',
        ];
    }
}
