<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\WhatsAppService;

$service = app(WhatsAppService::class);

// Using staff_account_created template as workaround (needs 4 variables)
$variables = [
    'Arun',  // Staff Name
    'Arun',  // Staff Name
    'arunfablead@gmail.com',  // Email
    'Lead',  // Role
];

echo "Sending WhatsApp template message to 918155864683...\n";
$result = $service->sendTemplate('918155864683', 'staff_account_created', $variables, 'lead', 39);

echo json_encode($result, JSON_PRETTY_PRINT) . "\n";

if ($result) {
    echo "\nTemplate message sent successfully!\n";
} else {
    echo "\nTemplate message failed to send.\n";
}
