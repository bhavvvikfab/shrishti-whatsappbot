<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMainAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !($user->is_active ?? true)) {
            abort(403);
        }

        // Allow admin-role users and any user explicitly granted
        // staff/role-management permissions.
        $managementPermissions = [
            'view_users',
            'create_users',
            'edit_users',
            'delete_users',
            'view_roles',
            'create_roles',
            'edit_roles',
            'delete_roles',
        ];

        $canManageStaff = $user->isAdmin()
            || $user->hasAnyPermission($managementPermissions);

        if (!$canManageStaff) {
            abort(403);
        }

        return $next($request);
    }
}
