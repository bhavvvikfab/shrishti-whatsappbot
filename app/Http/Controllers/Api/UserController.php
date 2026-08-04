<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Models\WhatsappConfig;
use App\Services\WhatsappConfigResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\QueryException;
use Throwable;
use Illuminate\Validation\Rule;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UserController extends ApiBaseController
{
    private function allowedMatrixPermissionNames(): array
    {
        $actions = array_keys(config('crm_permissions.actions', []));
        $names = [];

        foreach (config('crm_permissions.modules', []) as $module => $meta) {
            $moduleActions = $meta['actions'] ?? $actions;

            foreach ($moduleActions as $action) {
                $names[] = "{$action}_{$module}";
            }
        }

        return array_values(array_unique($names));
    }

    private function syncMatrixPermissions(User $user, array $permissions = []): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        // Preserve non-matrix permissions (like use_shared_whatsapp_config)
        $allowedMatrixNames = $this->allowedMatrixPermissionNames();
        $nonMatrixPermissions = $user->permissions()
            ->whereNotIn('name', $allowedMatrixNames)
            ->pluck('name')
            ->toArray();
            
        $allPermissions = array_merge($permissions, $nonMatrixPermissions);

        $user->syncPermissions($allPermissions);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function syncStaffWhatsappBot(User $user, Request $request): void
    {
        $mode = $request->input('whatsapp_bot_mode', WhatsappConfigResolver::MODE_NONE);
        $resolver = app(WhatsappConfigResolver::class);

        if ($mode === WhatsappConfigResolver::MODE_SHARED) {
            $resolver->grantSharedAccess($user);

            return;
        }

        $resolver->revokeSharedAccess($user);

        if ($mode !== WhatsappConfigResolver::MODE_OWN) {
            return;
        }

        $hasAnyCredential = filled($request->input('wa_app_id'))
            || filled($request->input('wa_phone_number_id'))
            || filled($request->input('wa_access_token'));

        if (! $hasAnyCredential) {
            return;
        }

        $existing = $resolver->ownConfigForUser($user);
        $rules = [
            'wa_app_id' => 'required|string',
            'wa_phone_number_id' => 'required|string',
            'wa_business_account_id' => 'required|string',
            'wa_access_token' => 'required|string',
            'wa_webhook_url' => 'nullable|string',
            'wa_verify_token' => 'nullable|string',
        ];

        if (! $existing || ! $existing->app_secret) {
            $rules['wa_app_secret'] = 'required|string';
        } else {
            $rules['wa_app_secret'] = 'nullable|string';
        }

        $waData = $request->validate($rules);

        $payload = [
            'user_id' => $user->id,
            'app_id' => $waData['wa_app_id'],
            'phone_number_id' => $waData['wa_phone_number_id'],
            'business_account_id' => $waData['wa_business_account_id'],
            'access_token' => $waData['wa_access_token'],
            'webhook_url' => $waData['wa_webhook_url'] ?? WhatsappConfig::webhookCallbackUrl(),
            'verify_token' => $waData['wa_verify_token'] ?? null,
            'modified_by' => auth()->id(),
        ];

        if (! empty($waData['wa_app_secret'])) {
            $payload['app_secret'] = $waData['wa_app_secret'];
        }

        if ($existing) {
            $existing->fill(collect($payload)->except('app_secret')->toArray());
            if (! empty($waData['wa_app_secret'])) {
                $existing->app_secret = $waData['wa_app_secret'];
            }
            $existing->save();
        } else {
            $payload['created_by'] = auth()->id();
            WhatsappConfig::create($payload);
        }
    }

    public function index(Request $request)
    {
        $search = trim((string) $request->get('search', ''));

        $users = User::with(['roles', 'permissions', 'whatsappConfig'])
            ->role('staff')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('address', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(10);

        $users->getCollection()->transform(function ($user) {
            $user->whatsapp_bot_mode = $user->whatsappConfigMode();
            return $user;
        });

        // dd($users);
        return response()->json([
            'success' => true,
            'message' => 'Users retrieved successfully.',
            'data' => $users,
        ]);
    }

    /**
     * Search users for dropdown/autocomplete.
     */
    public function search(Request $request)
    {
        $search = $request->get('q', $request->get('search', ''));
        
        $query = User::query()->where('is_active', 1);
        
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }
        
        $users = $query->limit(20)->get(['id', 'name', 'email']);
        
        return response()->json([
            'success' => true,
            'data' => $users->map(function ($user) {
                return [
                    'id' => $user->id,
                    'text' => $user->name,
                    'name' => $user->name,
                    'email' => $user->email,
                ];
            }),
        ]);
    }

    public function store(Request $request)
    {
        $allowedPermissions = $this->allowedMatrixPermissionNames();

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'phone' => ['required', 'regex:/^[0-9]{10}$/'],
            'whatsapp' => 'nullable|string|max:50',
            'address' => 'required|string',
            'image' => 'nullable|file|mimes:avif,webp,jpg,jpeg,png,gif,bmp,svg|max:2048',
            'permissions' => 'nullable|array',
            'permissions.*' => ['string', Rule::in($allowedPermissions)],
            'whatsapp_bot_mode' => ['nullable', Rule::in([
                WhatsappConfigResolver::MODE_NONE,
                WhatsappConfigResolver::MODE_OWN,
                WhatsappConfigResolver::MODE_SHARED,
            ])],
        ], [
            'phone.regex' => 'Phone number must be exactly 10 digits.',
            'image.mimes' => 'Please select a valid image! Allowed: AVIF, WEBP, JPG, JPEG, PNG, GIF, BMP, SVG.',
            'image.max' => 'Image size must not exceed 2MB.',
        ]);

        $avatarPath = null;
        if ($request->hasFile('image')) {
            $avatarPath = $request->file('image')->store('users', 'public');
        }

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'phone' => $data['phone'],
            'whatsapp' => $data['whatsapp'] ?? null,
            'address' => $data['address'],
            'avatar_path' => $avatarPath,
            'is_active' => true,
        ]);

        if ($staffRole = Role::where('name', 'staff')->first()) {
            $user->syncRoles([$staffRole->name]);
        }

        $this->syncMatrixPermissions($user, $data['permissions'] ?? []);
        $this->syncStaffWhatsappBot($user, $request);
        app(\App\Services\UserLogService::class)->created($user, 'Created a Staff ' . $user->name);

        try {
            $phone = $user->whatsapp ?: $user->phone;
            if ($phone) {
                $roleText = $user->roles()->pluck('name')->map(fn($role) => ucfirst($role))->implode(', ');
                if ($roleText === '') {
                    $roleText = 'Staff';
                }

                app(\App\Services\WhatsAppService::class)->sendForModule(
                    'staff_account_created',
                    $phone,
                    [
                        $user->name ?? 'Staff',
                        $user->name ?? 'Staff',
                        $user->email ?? 'N/A',
                        $roleText,
                    ],
                    $user->id
                );
            }
        } catch (\Throwable $e) {
            Log::error('Staff create WhatsApp block failed', [
                'user_id' => $user->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'User created successfully.',
            'data' => $user->load(['roles', 'permissions']),
            'redirect' => route('users.index'),
        ], 201);
    }

    public function show(User $user)
    {
        return response()->json([
            'success' => true,
            'message' => 'User retrieved successfully.',
            'data' => $user->load(['roles', 'permissions']),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $allowedPermissions = $this->allowedMatrixPermissionNames();

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', Rule::in([$user->email])],
            'password' => 'nullable|string|min:8',
            'phone' => ['required', 'regex:/^[0-9]{10}$/'],
            'whatsapp' => 'nullable|string|max:50',
            'address' => 'required|string',
            'image' => 'nullable|file|mimes:avif,webp,jpg,jpeg,png,gif,bmp,svg|max:2048',
            'permissions' => 'nullable|array',
            'permissions.*' => ['string', Rule::in($allowedPermissions)],
            'whatsapp_bot_mode' => ['nullable', Rule::in([
                WhatsappConfigResolver::MODE_NONE,
                WhatsappConfigResolver::MODE_OWN,
                WhatsappConfigResolver::MODE_SHARED,
            ])],
        ], [
            'email.in' => 'Email cannot be changed.',
            'phone.regex' => 'Phone number must be exactly 10 digits.',
            'image.mimes' => 'Please select a valid image! Allowed: AVIF, WEBP, JPG, JPEG, PNG, GIF, BMP, SVG.',
            'image.max' => 'Image size must not exceed 2MB.',
        ]);

        if ($request->hasFile('image')) {
            if ($user->avatar_path && Storage::disk('public')->exists($user->avatar_path)) {
                Storage::disk('public')->delete($user->avatar_path);
            }
            $user->avatar_path = $request->file('image')->store('users', 'public');
        }

        $user->name = $data['name'];
        $user->phone = $data['phone'];
        $user->whatsapp = $data['whatsapp'] ?? null;
        $user->address = $data['address'];
        if (!empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }
        $user->save();

        $this->syncMatrixPermissions($user, $data['permissions'] ?? []);
        $this->syncStaffWhatsappBot($user, $request);
        app(\App\Services\UserLogService::class)->updated($user, 'Updated a Staff ' . $user->name);

        try {
            $phone = $user->whatsapp ?: $user->phone;
            if ($phone) {
                $roleText = $user->roles()->pluck('name')->map(fn($role) => ucfirst($role))->implode(', ');
                if ($roleText === '') {
                    $roleText = 'Staff';
                }

                app(\App\Services\WhatsAppService::class)->sendForModule(
                    'staff_account_updated',
                    $phone,
                    [
                        $user->name ?? 'Staff',
                        $user->name ?? 'Staff',
                        $user->email ?? 'N/A',
                        $roleText,
                    ],
                    $user->id
                );
            }
        } catch (\Throwable $e) {
            Log::error('Staff update WhatsApp block failed', [
                'user_id' => $user->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully.',
            'data' => $user->load(['roles', 'permissions']),
            'redirect' => route('users.index'),
        ]);
    }

    public function destroy(User $user)
    {
        if (auth()->id() === $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot delete yourself.',
            ], 422);
        }

        try {
            if ($user->avatar_path && Storage::disk('public')->exists($user->avatar_path)) {
                Storage::disk('public')->delete($user->avatar_path);
            }

            app(\App\Services\UserLogService::class)->deleted($user, 'Deleted a Staff ' . $user->name);
            $user->delete();

            return response()->json([
                'success' => true,
                'message' => 'User deleted successfully.',
            ]);
        } catch (QueryException $exception) {
            // Foreign-key protected records exist for this user in other modules.
            $isConstraintError = (string) $exception->getCode() === '23000'
                || (int) ($exception->errorInfo[1] ?? 0) === 1451;

            if ($isConstraintError) {
                return response()->json([
                    'success' => false,
                    'message' => 'Module in use. This staff is linked with existing records, so it cannot be deleted.',
                ], 422);
            }

            return response()->json([
                'success' => false,
                'message' => 'Unable to delete staff right now. Please try again.',
            ], 500);
        } catch (Throwable $exception) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to delete staff right now. Please try again.',
            ], 500);
        }
    }

    public function updateStatus(Request $request, User $user)
    {
        if (auth()->id() === $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot change your own status.',
            ], 422);
        }

        $data = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $user->is_active = (bool) $data['is_active'];
        $user->save();
        app(\App\Services\UserLogService::class)->updated($user, 'Updated a Staff ' . $user->name);

        return response()->json([
            'success' => true,
            'message' => $user->name . ' marked as ' . ($user->is_active ? 'active' : 'inactive') . '.',
            'is_active' => (bool) $user->is_active,
        ]);
    }

    public function updatePermissions(Request $request, User $user)
    {
        $allowedPermissions = $this->allowedMatrixPermissionNames();

        $data = $request->validate([
            'permissions' => 'nullable|array',
            'permissions.*' => [
                'string',
                Rule::in($allowedPermissions),
            ],
        ]);

        $this->syncMatrixPermissions($user, $data['permissions'] ?? []);
        app(\App\Services\UserLogService::class)->updated($user, 'Updated a Staff ' . $user->name);

        return response()->json([
            'success' => true,
            'message' => 'Permissions updated successfully.',
        ]);
    }
}
