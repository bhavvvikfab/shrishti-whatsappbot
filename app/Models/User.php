<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\WhatsappConfig;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'whatsapp',
        'address',
        'job_title',
        'company',
        'city',
        'country',
        'avatar_path',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_active' => 'boolean',
    ];

    protected $appends = [
        'avatar_url',
    ];

    public function isMainAdmin(): bool
    {
        return $this->isAdmin();
    }

    public function isAdmin(): bool
    {
        return $this->hasAdminRole();
    }

    public function hasMatrixPermission(string $permission): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        return $this->permissions()->where('name', $permission)->exists();
    }

    public function whatsappConfig()
    {
        return $this->hasOne(WhatsappConfig::class);
    }

    public function whatsappConfigMode(): string
    {
        return app(\App\Services\WhatsappConfigResolver::class)->resolveMode($this);
    }

    public function activeWhatsappConfig(): ?WhatsappConfig
    {
        return WhatsappConfig::forUser($this);
    }

    public function canUseWhatsappInbox(): bool
    {
        return app(\App\Services\WhatsappConfigResolver::class)->canAccessWhatsapp($this);
    }

    public function assignedDeals()
    {
        return $this->hasMany(Deal::class, 'assigned_user_id');
    }

    protected function hasAdminRole(): bool
    {
        return $this->roles()->whereIn('name', ['admin', 'super-admin'])->exists();
    }

    public function getAvatarUrlAttribute(): string
    {
        $default = 'https://ui-avatars.com/api/?name=' . urlencode((string) $this->name) . '&background=3b82f6&color=ffffff&size=128';

        if (empty($this->avatar_path)) {
            return $default;
        }

        $path = ltrim((string) $this->avatar_path, '/');

        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        if (Str::startsWith($path, 'storage/')) {
            $publicPath = public_path($path);
            return file_exists($publicPath) ? asset($path) : $default;
        }

        if (Storage::disk('public')->exists($path)) {
            return asset('storage/' . $path);
        }

        $publicPath = public_path($path);
        return file_exists($publicPath) ? asset($path) : $default;
    }
}
