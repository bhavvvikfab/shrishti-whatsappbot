<?php

/**
 * WhatsApp / Meta Cloud API webhooks — loaded without the `web` middleware group
 * (no session, CSRF, or CRM auth middleware) so Meta can POST reliably.
 */

use App\Http\Controllers\WhatsappWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/whatsapp-configration/webhook', [WhatsappWebhookController::class, 'verify']);
Route::post('/whatsapp-configration/webhook', [WhatsappWebhookController::class, 'handle']);
Route::get('/whatapp-configration/webhook', [WhatsappWebhookController::class, 'verify']);
Route::post('/whatapp-configration/webhook', [WhatsappWebhookController::class, 'handle']);
