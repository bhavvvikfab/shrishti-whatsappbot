<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\WhatsAppService;

$service = app(WhatsAppService::class);

// Using lead_welcome template (you need to create this first)
$variables = [
    'Hello Arun, welcome to our service!',  // Lead Name
];

echo "Sending WhatsApp template message to 918155864683...\n";
$result = $service->sendTextMessage('918155864683', 'Hello Arun, welcome to our service!');

echo json_encode($result, JSON_PRETTY_PRINT) . "\n";

if ($result) {
    echo "\nTemplate message sent successfully!\n";
} else {
    echo "\nTemplate message failed to send.\n";
}
