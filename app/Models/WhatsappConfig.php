<?php

namespace App\Models;

use App\Traits\Blameable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WhatsappConfig extends Model
{
    use SoftDeletes, Blameable;

    protected $table = 'whatsapp_config';

    protected $fillable = [
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

    public static function current(): ?self
    {
        return static::first();
    }

    public static function webhookCallbackUrl(): string
    {
        return url('/whatsapp-configration/webhook');
    }

    /**
     * Token Meta sends on webhook GET verify — settings DB first, then .env.
     */
    public static function resolveWebhookVerifyToken(): string
    {
        $fromDb = static::query()->value('verify_token');

        if (filled($fromDb)) {
            return (string) $fromDb;
        }

        return (string) config('services.whatsapp.verify_token', 'fablead_whatsapp_verify');
    }
}
