<?php

namespace App\Services;

use App\Models\User;
use App\Models\WhatsappConfig;

class WhatsappConfigResolver
{
    public const PERMISSION_SHARED = 'use_shared_whatsapp_config';

    public const MODE_NONE = 'none';

    public const MODE_OWN = 'own';

    public const MODE_SHARED = 'shared';

    public function forUser(?User $user = null): ?WhatsappConfig
    {
        if ($user === null) {
            $user = auth()->user();
        }

        if ($user === null) {
            return $this->adminConfig();
        }

        if ($user->isAdmin()) {
            $own = $this->ownConfigForUser($user);
            return $own ?? $this->adminConfig();
        }

        $own = $this->ownConfigForUser($user);
        if ($own) {
            return $own;
        }

        if ($user->hasMatrixPermission(self::PERMISSION_SHARED)) {
            return $this->adminConfig();
        }

        return null;
    }

    public function adminConfig(): ?WhatsappConfig
    {
        $admin = User::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['admin', 'super-admin']))
            ->orderBy('id')
            ->first();

        if ($admin) {
            $config = $this->ownConfigForUser($admin);
            if ($config) {
                return $config;
            }
        }

        return WhatsappConfig::query()->orderBy('id')->first();
    }

    public function ownConfigForUser(User $user): ?WhatsappConfig
    {
        return WhatsappConfig::query()->where('user_id', $user->id)->first();
    }

    public function byPhoneNumberId(?string $phoneNumberId): ?WhatsappConfig
    {
        if (! filled($phoneNumberId)) {
            return null;
        }

        return WhatsappConfig::query()
            ->where('phone_number_id', $phoneNumberId)
            ->first();
    }

    public function resolveMode(User $user): string
    {
        if ($this->ownConfigForUser($user)) {
            return self::MODE_OWN;
        }

        if ($user->hasMatrixPermission(self::PERMISSION_SHARED)) {
            return self::MODE_SHARED;
        }

        return self::MODE_NONE;
    }

    public function canAccessWhatsapp(User $user): bool
    {
        if ($user->isAdmin()) {
            return $this->forUser($user) !== null;
        }

        if (! $user->hasMatrixPermission('view_whatsapp')) {
            return false;
        }

        return $this->forUser($user) !== null;
    }

    public function grantSharedAccess(User $user): void
    {
        $user->givePermissionTo(self::PERMISSION_SHARED);
        $this->ownConfigForUser($user)?->delete();
    }

    public function revokeSharedAccess(User $user): void
    {
        $user->revokePermissionTo(self::PERMISSION_SHARED);
    }
}
