<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$token = App\Models\PartnerAccessToken::whereNull('used_at')
    ->where('expires_at', '>', now())
    ->latest()
    ->first();

if (!$token) {
    echo "No active partner token found\n";
    exit;
}

echo "Task ID: {$token->task_id}\n";
echo "Step ID: {$token->workflow_step_id}\n";

$docs = App\Models\TaskDocument::where('task_id', $token->task_id)->get();
foreach ($docs as $d) {
    $exists = Illuminate\Support\Facades\Storage::disk('local')->exists($d->path) ? 'EXISTS' : 'MISSING';
    echo "Doc #{$d->id}: v{$d->version} | {$d->path} | {$d->mime_type} | {$exists}\n";
}
