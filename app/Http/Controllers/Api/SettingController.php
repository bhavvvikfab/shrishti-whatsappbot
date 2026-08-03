<?php

namespace App\Http\Controllers\Api;

use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends ApiBaseController
{
    private const MANAGED_KEYS = [
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
    ];

    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (!auth()->user()?->isAdmin()) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access to settings.'], 403);
            }
            return $next($request);
        });
    }

    public function index(Request $request)
    {
        $group = $request->get('group');
        $query = Setting::query();

        if ($group) {
            $query->where('group', $group);
        } else {
            $query->whereIn('key', self::MANAGED_KEYS);
        }

        $settings = $query->pluck('value', 'key');

        return $this->success([
            'settings' => $settings,
        ]);
    }

    public function update(Request $request)
    {
        if ($request->has('settings') && is_array($request->settings)) {
            $request->validate([
                'settings' => 'required|array',
                'settings.*.key' => 'required|string|in:' . implode(',', self::MANAGED_KEYS),
                'settings.*.value' => 'nullable',
            ]);

            foreach ($request->settings as $item) {
                Setting::updateOrCreate(
                    ['key' => $item['key']],
                    ['value' => is_array($item['value']) ? json_encode($item['value']) : $item['value']]
                );
            }
        } else {
            // Handle flat inputs (e.g. from FormData)
            $data = $request->except(['_token', '_method']);
            
            if (empty($data)) {
                return $this->error('No settings data provided', 422);
            }

            foreach ($data as $key => $value) {
                // Skip if it's a file for now (files are handled better in the web controller)
                // but at least update non-file values
                if ($request->hasFile($key)) {
                    continue;
                }

                if (in_array($key, self::MANAGED_KEYS, true)) {
                    Setting::updateOrCreate(
                        ['key' => $key],
                        ['value' => is_array($value) ? json_encode($value) : $value]
                    );
                }
            }
        }

        return $this->success(null, 'Settings updated successfully');
    }

    public function show($key)
    {
        $setting = Setting::where('key', $key)->firstOrFail();
        return $this->success($setting);
    }
}
