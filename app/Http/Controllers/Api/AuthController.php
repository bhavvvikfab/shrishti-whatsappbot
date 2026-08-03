<?php

namespace App\Http\Controllers\Api;


use App\Support\SanctumLogin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends ApiBaseController
{
    public function login(Request $request)
    {
        dd('ff');
        // Accept both JSON and form data
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'device_name' => 'nullable|string|max:255',
        ]);

        // Fetch active user by email
        $user = SanctumLogin::findActiveUserByEmail($request->string('email')->toString());

        // Check credentials
        if (!$user || !Hash::check($request->string('password')->toString(), $user->password)) {
            return $this->error('Invalid credentials', 401);
        }

        // Generate token with device name (auto-detect if not provided)
        $deviceName = $request->string('device_name')->toString() 
            ?: SanctumLogin::defaultDeviceName($request, 'web-app');
        
        $token = SanctumLogin::issueToken($user, $deviceName);

        // Return success response
        return $this->success([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'is_active' => (bool) ($user->is_active ?? true),
            ],
        ], 'Login successful');
    }

    public function logout(Request $request)
    {
        $request->user()?->currentAccessToken()?->delete();

        return $this->success(null, 'Logged out successfully');
    }

    public function me(Request $request)
    {
        return $this->success($request->user());
    }
}
