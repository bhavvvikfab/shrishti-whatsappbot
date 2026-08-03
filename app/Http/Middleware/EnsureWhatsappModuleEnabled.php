<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;

class EnsureWhatsappModuleEnabled
{
    public function handle(Request $request, Closure $next)
    {
        if (Setting::isEnabled('whatsapp_module_enabled', true)) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'success' => false,
                'message' => 'WhatsApp module is disabled.',
            ], 403);
        }

        abort(404);
    }
}
