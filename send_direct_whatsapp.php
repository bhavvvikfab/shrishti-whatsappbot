<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\WhatsAppService;

$service = app(WhatsAppService::class);

$message = "Hi Arun! Thanks for your interest in WhatsApp marketing for your salon. I'd love to discuss how we can help grow your business. When would be a good time to chat?";

echo "Sending WhatsApp message to 918155864683...\n";
$result = $service->sendTextMessage('918155864683', $message);

echo json_encode($result, JSON_PRETTY_PRINT) . "\n";

if ($result['success']) {
    echo "\nMessage sent successfully!\n";
} else {
    echo "\nMessage failed to send. Error: " . ($result['error'] ?? 'Unknown') . "\n";
}
