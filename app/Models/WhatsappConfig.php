<?php

namespace App\Models;

use App\Services\WhatsappConfigResolver;
use App\Traits\Blameable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WhatsappConfig extends Model
{
    use SoftDeletes, Blameable;

    protected $table = 'whatsapp_config';

    protected $fillable = [
        'user_id',
        'app_id',
        'app_secret',
        'phone_number_id',
        'business_account_id',
        'access_token',
        'webhook_url',
        'verify_token',
        'created_by',
        'modified_by',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Active WhatsApp config for the authenticated user (or explicit user).
     */
    public static function forUser(?User $user = null): ?self
    {
        return app(WhatsappConfigResolver::class)->forUser($user);
    }

    /**
     * Legacy helper — resolves config for the current user, or admin config when unauthenticated.
     */
    public static function current(): ?self
    {
        return static::forUser();
    }

    public static function adminConfig(): ?self
    {
        return app(WhatsappConfigResolver::class)->adminConfig();
    }

    public static function byPhoneNumberId(?string $phoneNumberId): ?self
    {
        return app(WhatsappConfigResolver::class)->byPhoneNumberId($phoneNumberId);
    }

    public static function webhookCallbackUrl(): string
    {
        return url('/whatsapp-configration/webhook');
    }

    /**
     * Token Meta sends on webhook GET verify — admin config DB first, then .env.
     */
    public static function resolveWebhookVerifyToken(): string
    {
        $fromDb = static::adminConfig()?->verify_token;

        if (filled($fromDb)) {
            return (string) $fromDb;
        }

        return (string) config('services.whatsapp.verify_token', 'fablead_whatsapp_verify');
    }
}
