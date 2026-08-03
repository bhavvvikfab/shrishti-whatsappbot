<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Support\SanctumLogin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLoginForm(Request $request): View|RedirectResponse|Response
    {
        if (Auth::check()) {
            return redirect()->route('whatsapp.inbox');
        }

        $request->session()->regenerateToken();

        return response()
            ->view('auth.login', $this->loginBranding())
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', 'Sat, 01 Jan 2000 00:00:00 GMT');
    }

    /**
     * Company name for the login screen footer. The logo is a static asset so it
     * stays the Fablead mark regardless of the uploaded company logo.
     */
    private function loginBranding(): array
    {
        return [
            'companyName' => Setting::getValue('company_name') ?: 'Fablead Developers Technolab',
        ];
    }

    public function login(Request $request): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        
        // Validate input
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $email = $request->string('email')->toString();
        $password = $request->string('password')->toString();

        // Find active user
        $user = SanctumLogin::findActiveUserByEmail($email);

        // Check credentials
        if (!$user || !Hash::check($password, $user->password)) {
            Log::warning('Login failed: Invalid credentials', ['email' => $email]);
            
            // If API request, return JSON
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Invalid credentials'], 401);
            }
            return back()->withErrors(['email' => 'Invalid credentials']);
        }

        // Authenticate user (session-based)
        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        // Generate API token for this session
        $deviceName = SanctumLogin::defaultDeviceName($request, 'web-session');
        
        try {
            $token = SanctumLogin::issueToken($user, $deviceName);
            
            Log::info('Token generated successfully', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'token_prefix' => substr($token, 0, 20),
            ]);
        } catch (\Exception $e) {
            Log::error('Token generation failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            
            // Continue without token - session auth still works
            $token = null;
        }

        // Set token in cookie (30 days) if token was generated
        $cookie = null;
        if ($token) {
            $date = new \DateTime();
            $date->modify('+30 days');
            $cookie = cookie('crm_auth_token', $token, $date->getTimestamp() / 60, '/', null, false, true);
        }

        Log::info('User logged in', [
            'user_id' => $user->id,
            'user_name' => $user->name,
            'user_email' => $user->email,
            'user_roles' => $user->roles()->pluck('name')->toArray(),
            'token_generated' => $token !== null,
        ]);

        // If API request, return JSON with token
        if ($request->expectsJson()) {
            $response = response()->json([
                'success' => true,
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'is_active' => (bool) ($user->is_active ?? true),
                    'roles' => $user->roles()->pluck('name')->toArray(),
                ],
            ], 200);
            
            if ($cookie) {
                $response->cookie($cookie);
            }
            
            return $response;
        }

        $redirect = redirect()->route('whatsapp.inbox');

        if ($cookie) {
            $redirect->cookie($cookie);
        }
        
        return $redirect;
    }

    public function logout(Request $request): RedirectResponse
    {
        $token = $request->cookie('crm_auth_token');

        if ($token) {
            try {
                PersonalAccessToken::findToken($token)?->delete();
            } catch (\Exception $e) {
                Log::error('API logout failed', ['error' => $e->getMessage()]);
            }
        }

        // Also delete any tokens for current user's session
        if (Auth::check()) {
            try {
                Auth::user()->tokens()->delete();
            } catch (\Exception $e) {
                Log::error('User tokens deletion failed', ['error' => $e->getMessage()]);
            }
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Clear the auth token cookie
        $response = redirect()->route('login');
        $response->cookie(cookie()->forget('crm_auth_token'));
        
        return $response;
    }
}
