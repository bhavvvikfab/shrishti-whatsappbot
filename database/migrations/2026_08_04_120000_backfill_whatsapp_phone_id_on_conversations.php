<?php

use App\Models\WhatsappConfig;
use App\Models\WhatsappConversation;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $phoneNumberId = WhatsappConfig::adminConfig()?->phone_number_id;

        if (! filled($phoneNumberId)) {
            return;
        }

        WhatsappConversation::query()
            ->whereNull('whatsapp_phone_id')
            ->update(['whatsapp_phone_id' => $phoneNumberId]);
    }

    public function down(): void
    {
        // Non-reversible data fix.
    }
};
