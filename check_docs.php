<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$service = app(\App\Services\AiWorkflowService::class);
echo "AiWorkflowService loaded OK\n";
echo "AI available: " . ($service->isAvailable() ? 'YES' : 'NO') . "\n";

$apiKey = \App\Models\Setting::get('openai_api_key', '');
echo "API key configured: " . (strlen($apiKey) > 10 ? 'YES (' . strlen($apiKey) . ' chars)' : 'NO') . "\n";
