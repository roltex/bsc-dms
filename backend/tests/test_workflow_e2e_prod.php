<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Enums\TaskStatus;
use App\Models\DocumentCategory;
use App\Models\Partner;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkflowRoute;
use App\Services\WorkflowEngine;

echo "=== EFES DMS - Production Workflow E2E Test ===\n\n";

$pass = 0;
$fail = 0;
function assert_eq($label, $expected, $actual) {
    global $pass, $fail;
    if ($expected === $actual) {
        echo "  [PASS] {$label}\n";
        $pass++;
    } else {
        echo "  [FAIL] {$label} - Expected: " . json_encode($expected) . ", Got: " . json_encode($actual) . "\n";
        $fail++;
    }
}

echo "--- Phase 1: Verify Routes ---\n";
$routes = WorkflowRoute::where('is_active', true)->get();
assert_eq('Active routes >= 2', true, $routes->count() >= 2);

$newRoute = WorkflowRoute::where('slug', 'new')->first();
assert_eq('New Route exists', true, $newRoute !== null);
assert_eq('New Route has 6 steps', 6, $newRoute ? $newRoute->steps()->count() : 0);
assert_eq('New Route has transitions', true, $newRoute ? $newRoute->transitions()->count() > 0 : false);

echo "\n--- Phase 2: Test Full Flow with New Route ---\n";
$initiator = User::where('role', 'initiator')->first();
$manager = User::where('role', 'manager')->first();
$lawyer = User::where('role', 'lawyer')->first();
$category = DocumentCategory::first();
$partner = Partner::first();

if (!$initiator || !$manager || !$lawyer || !$category || !$partner || !$newRoute) {
    echo "  Missing required data. Aborting.\n";
    exit(1);
}

$task = Task::create([
    'document_category_id' => $category->id,
    'partner_id' => $partner->id,
    'initiator_id' => $initiator->id,
    'assigned_lawyer_id' => $lawyer->id,
    'route_type' => $newRoute->slug,
    'workflow_route_id' => $newRoute->id,
    'status' => TaskStatus::Draft,
    'current_step' => 0,
]);
assert_eq('Task created', true, $task->id > 0);

$engine = app(WorkflowEngine::class);
$engine->submit($task, $initiator);
$task->refresh();
assert_eq('After submit, status=pending_initiator', 'pending_initiator', $task->status->value);
assert_eq('After submit, step=1', 1, $task->current_step);

$expectedFlow = [
    ['actor' => 'initiator', 'next_status' => 'pending_manager', 'next_step' => 2],
    ['actor' => 'manager',   'next_status' => 'pending_lawyer',  'next_step' => 3],
    ['actor' => 'lawyer',    'next_status' => 'pending_initiator','next_step' => 4],
    ['actor' => 'initiator', 'next_status' => 'pending_lawyer',  'next_step' => 5],
    ['actor' => 'lawyer',    'next_status' => 'pending_manager',  'next_step' => 6],
    ['actor' => 'manager',   'next_status' => 'approved',        'next_step' => 6],
];

$actors = ['initiator' => $initiator, 'manager' => $manager, 'lawyer' => $lawyer];

foreach ($expectedFlow as $i => $exp) {
    $stepNum = $i + 1;
    $actor = $actors[$exp['actor']];
    $result = $engine->advance($task, $actor, "Step {$stepNum} done");
    $task->refresh();
    assert_eq("Step {$stepNum}: advance success", true, $result['success']);
    assert_eq("Step {$stepNum}: status={$exp['next_status']}", $exp['next_status'], $task->status->value);
    if ($exp['next_status'] !== 'approved') {
        assert_eq("Step {$stepNum}: current_step={$exp['next_step']}", $exp['next_step'], $task->current_step);
    }
}

echo "\n--- Phase 3: Test Standard & Simplified ---\n";
foreach (['standard', 'simplified'] as $slug) {
    $route = WorkflowRoute::where('slug', $slug)->first();
    if (!$route) { echo "  [SKIP] {$slug} route not found\n"; continue; }

    $t = Task::create([
        'document_category_id' => $category->id,
        'partner_id' => $partner->id,
        'initiator_id' => $initiator->id,
        'assigned_lawyer_id' => $lawyer->id,
        'route_type' => $slug,
        'workflow_route_id' => $route->id,
        'status' => TaskStatus::Draft,
        'current_step' => 0,
    ]);
    $engine->submit($t, $initiator);
    $t->refresh();

    $steps = 0;
    while ($t->status !== TaskStatus::Approved && $steps < 10) {
        $step = $engine->getCurrentStep($t);
        $actor = $actors[$step->role] ?? $initiator;
        $engine->advance($t, $actor);
        $t->refresh();
        $steps++;
    }
    assert_eq("{$slug}: completed in {$steps} steps", 'approved', $t->status->value);
    $t->activities()->delete();
    $t->delete();
}

echo "\n--- Phase 4: API Response Format ---\n";
$apiRoutes = WorkflowRoute::where('is_active', true)->with(['steps' => fn($q) => $q->orderBy('sort_order')])->get();
foreach ($apiRoutes as $r) {
    assert_eq("Route '{$r->name}' has steps", true, $r->steps->count() > 0);
    foreach ($r->steps as $s) {
        assert_eq("  Step '{$s->name}' valid role", true, in_array($s->role, ['manager','lawyer','initiator']));
    }
}

echo "\n--- Cleanup ---\n";
$task->activities()->delete();
$task->delete();
echo "  Done.\n";

echo "\n=============================\n";
echo "RESULTS: {$pass} passed, {$fail} failed\n";
echo "=============================\n";
exit($fail > 0 ? 1 : 0);
