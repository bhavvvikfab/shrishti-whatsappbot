<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Jobs\SendWhatsAppMessageJob;
use App\Models\Lead;

// Get lead 39
$lead = Lead::find(39);

if (!$lead) {
    echo "Lead 39 not found!\n";
    exit(1);
}

echo "Lead found: {$lead->name}\n";
echo "Phone: {$lead->phone}\n";
echo "WhatsApp: {$lead->whatsapp}\n";
echo "Notes: {$lead->notes}\n\n";

// Use a static personalized message
$message = "Hi {$lead->name}! Thanks for your interest in WhatsApp marketing for your salon. I'd love to discuss how we can help grow your business. When would be a good time to chat?";

echo "Message: {$message}\n\n";

// Queue the WhatsApp message job
$phoneNumber = $lead->whatsapp ?: $lead->phone;

echo "Queuing WhatsApp message job for: {$phoneNumber}\n";

SendWhatsAppMessageJob::dispatch($phoneNumber, $message);

echo "Job queued successfully!\n";
echo "Make sure your queue worker is running: php artisan queue:work\n";
