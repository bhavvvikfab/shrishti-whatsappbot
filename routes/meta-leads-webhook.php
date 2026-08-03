<?php

use App\Http\Controllers\MetaLeadWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/meta-leads/webhook', [MetaLeadWebhookController::class, 'verify']);
Route::post('/meta-leads/webhook', [MetaLeadWebhookController::class, 'handle']);
