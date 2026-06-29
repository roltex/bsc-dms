<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Enums\TaskStatus;
use App\Models\DocumentCategory;
use App\Models\Partner;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkflowRoute;
use App\Models\WorkflowStep;
use App\Models\WorkflowTransition;
use App\Services\WorkflowEngine;

echo "=== EFES DMS - Workflow E2E Test ===\n\n";

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

// ========== PHASE 1: Verify existing routes ==========
echo "--- Phase 1: Verify Workflow Routes ---\n";
$routes = WorkflowRoute::where('is_active', true)->get();
assert_eq('Active routes exist', true, $routes->count() >= 2);

$standardRoute = WorkflowRoute::where('slug', 'standard')->first();
$simplifiedRoute = WorkflowRoute::where('slug', 'simplified')->first();
$newRoute = WorkflowRoute::where('slug', 'new')->first();

assert_eq('Standard route exists', true, $standardRoute !== null);
assert_eq('Simplified route exists', true, $simplifiedRoute !== null);
assert_eq('New Route exists', true, $newRoute !== null);
assert_eq('Standard route active', true, $standardRoute->is_active);
assert_eq('New Route active', true, $newRoute?->is_active ?? false);
assert_eq('New Route has 6 steps', 6, $newRoute?->steps()->count() ?? 0);

echo "\n--- Phase 1b: Verify Route Steps & Transitions ---\n";
$newRouteSteps = $newRoute->steps()->orderBy('sort_order')->get();
echo "  New Route steps:\n";
foreach ($newRouteSteps as $s) {
    echo "    Step {$s->sort_order}: {$s->name} (role={$s->role}, action={$s->action_type})\n";
}
$transitions = $newRoute->transitions;
echo "  Transitions: " . $transitions->count() . "\n";
assert_eq('New Route has 5 transitions', 5, $transitions->count());

$firstStep = $newRoute->firstStep();
$lastStep = $newRoute->lastStep();
assert_eq('First step is Initiator Action (review)', true, $firstStep->role === 'initiator' && $firstStep->action_type === 'review');
assert_eq('Last step is Final Approval (manager, submit)', true, $lastStep->role === 'manager' && $lastStep->action_type === 'submit');

// ========== PHASE 2: Create a test task with New Route ==========
echo "\n--- Phase 2: Create Task with Dynamic Workflow Route ---\n";

$initiator = User::where('role', 'initiator')->first();
$manager = User::where('role', 'manager')->first();
$lawyer = User::where('role', 'lawyer')->first();
$admin = User::where('role', 'administrator')->first();

if (!$initiator || !$manager || !$lawyer) {
    echo "  [SKIP] Need at least one user of each role (initiator, manager, lawyer)\n";
    echo "\nResults: {$pass} passed, {$fail} failed\n";
    exit($fail > 0 ? 1 : 0);
}

echo "  Users: initiator={$initiator->name}(#{$initiator->id}), manager={$manager->name}(#{$manager->id}), lawyer={$lawyer->name}(#{$lawyer->id})\n";

$category = DocumentCategory::first();
$partner = Partner::first();

if (!$category || !$partner) {
    echo "  [SKIP] Need at least one category and partner\n";
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

assert_eq('Task created in draft', 'draft', $task->status->value);
assert_eq('Task linked to New Route', $newRoute->id, $task->workflow_route_id);
assert_eq('Task route_type is "new"', 'new', $task->route_type);

// ========== PHASE 3: Submit task (initiator -> first step) ==========
echo "\n--- Phase 3: Submit Task (Initiator submits) ---\n";
$engine = app(WorkflowEngine::class);
$engine->submit($task, $initiator);
$task->refresh();

$expectedFirstStatus = TaskStatus::PendingInitiator; // first step: initiator, review -> PendingInitiator
assert_eq('After submit, status = pending_initiator', 'pending_initiator', $task->status->value);
assert_eq('After submit, current_step = 1', 1, $task->current_step);
assert_eq('After submit, current_workflow_step_id set', $firstStep->id, $task->current_workflow_step_id);

// ========== PHASE 4: Advance through all steps ==========
echo "\n--- Phase 4: Walk Through All Steps ---\n";

// Step 1: Initiator Action (review) - PendingInitiator -> next step
echo "  Step 1: Initiator Action (review) - current status: {$task->status->value}\n";
$currentStep = $engine->getCurrentStep($task);
assert_eq('Current step = Initiator Action', 'Initiator Action', $currentStep->name);
assert_eq('Current step role = initiator', 'initiator', $currentStep->role);

// Test that wrong user can't act
$canManagerAct = $engine->canActOnStep($currentStep, $manager, $task);
assert_eq('Manager CANNOT act on initiator step', false, $canManagerAct);
$canInitiatorAct = $engine->canActOnStep($currentStep, $initiator, $task);
assert_eq('Initiator CAN act on initiator step', true, $canInitiatorAct);

$result = $engine->advance($task, $initiator, 'Initiator reviewed');
$task->refresh();
assert_eq('Advance step 1 success', true, $result['success']);
assert_eq('After step 1, status = pending_manager', 'pending_manager', $task->status->value);
assert_eq('After step 1, current_step = 2', 2, $task->current_step);

// Step 2: Manager Review (approve) - PendingManager -> next step
echo "  Step 2: Manager Review (approve) - current status: {$task->status->value}\n";
$currentStep = $engine->getCurrentStep($task);
assert_eq('Current step = Manager Review', 'Manager Review', $currentStep->name);

$canLawyerAct = $engine->canActOnStep($currentStep, $lawyer, $task);
assert_eq('Lawyer CANNOT act on manager step', false, $canLawyerAct);
$canManagerAct = $engine->canActOnStep($currentStep, $manager, $task);
assert_eq('Manager CAN act on manager step', true, $canManagerAct);

$result = $engine->advance($task, $manager, 'Manager approved');
$task->refresh();
assert_eq('Advance step 2 success', true, $result['success']);
assert_eq('After step 2, status = pending_lawyer', 'pending_lawyer', $task->status->value);
assert_eq('After step 2, current_step = 3', 3, $task->current_step);

// Step 3: Lawyer Review (approve) - PendingLawyer -> next step
echo "  Step 3: Lawyer Review (approve) - current status: {$task->status->value}\n";
$currentStep = $engine->getCurrentStep($task);
assert_eq('Current step = Lawyer Review', 'Lawyer Review', $currentStep->name);

$result = $engine->advance($task, $lawyer, 'Lawyer approved');
$task->refresh();
assert_eq('Advance step 3 success', true, $result['success']);
assert_eq('After step 3, status = pending_initiator (sign step)', 'pending_initiator', $task->status->value);
assert_eq('After step 3, current_step = 4', 4, $task->current_step);

// Step 4: Initiator Action (sign) - PendingInitiator -> next step
echo "  Step 4: Initiator Action (sign) - current status: {$task->status->value}\n";
$currentStep = $engine->getCurrentStep($task);
assert_eq('Current step = Initiator Action (sign)', 'sign', $currentStep->action_type);

$result = $engine->advance($task, $initiator, 'Initiator signed');
$task->refresh();
assert_eq('Advance step 4 success', true, $result['success']);
assert_eq('After step 4, status = pending_lawyer', 'pending_lawyer', $task->status->value);
assert_eq('After step 4, current_step = 5', 5, $task->current_step);

// Step 5: Lawyer Review (review) - PendingLawyer -> next step
echo "  Step 5: Lawyer Review (review) - current status: {$task->status->value}\n";
$currentStep = $engine->getCurrentStep($task);
assert_eq('Current step = Lawyer Review', 'Lawyer Review', $currentStep->name);

$result = $engine->advance($task, $lawyer, 'Final lawyer review done');
$task->refresh();
assert_eq('Advance step 5 success', true, $result['success']);
assert_eq('After step 5, status = pending_manager', 'pending_manager', $task->status->value);
assert_eq('After step 5, current_step = 6', 6, $task->current_step);

// Step 6: Final Approval (manager, submit) - PendingManager -> Approved (terminal)
echo "  Step 6: Final Approval (manager) - current status: {$task->status->value}\n";
$currentStep = $engine->getCurrentStep($task);
assert_eq('Current step = Final Approval', 'Final Approval', $currentStep->name);

$result = $engine->advance($task, $manager, 'Final approval granted');
$task->refresh();
assert_eq('Advance step 6 success', true, $result['success']);
assert_eq('Step 6 is terminal', true, $result['terminal']);
assert_eq('After step 6, status = approved', 'approved', $task->status->value);

// ========== PHASE 5: Test with Standard Route ==========
echo "\n--- Phase 5: Quick Test with Standard Route ---\n";

$stdTask = Task::create([
    'document_category_id' => $category->id,
    'partner_id' => $partner->id,
    'initiator_id' => $initiator->id,
    'assigned_lawyer_id' => $lawyer->id,
    'route_type' => 'standard',
    'workflow_route_id' => $standardRoute->id,
    'status' => TaskStatus::Draft,
    'current_step' => 0,
]);

$engine->submit($stdTask, $initiator);
$stdTask->refresh();
$stdFirstStep = $standardRoute->firstStep();
echo "  Standard route first step: {$stdFirstStep->name} (role={$stdFirstStep->role})\n";
echo "  After submit, status: {$stdTask->status->value}, current_step: {$stdTask->current_step}\n";
assert_eq('Standard: after submit, has current_workflow_step_id', true, $stdTask->current_workflow_step_id !== null);

// Walk through standard route
$stepCount = 0;
while ($stdTask->status !== TaskStatus::Approved && $stepCount < 10) {
    $step = $engine->getCurrentStep($stdTask);
    $actor = match ($step->role) {
        'manager' => $manager,
        'lawyer' => $lawyer,
        'initiator' => $initiator,
        default => $admin,
    };
    $result = $engine->advance($stdTask, $actor, "Step {$step->sort_order} done");
    $stdTask->refresh();
    $stepCount++;
    echo "  Advanced step {$step->sort_order} ({$step->name}): status={$stdTask->status->value}\n";
}
assert_eq('Standard route completed', 'approved', $stdTask->status->value);
assert_eq('Standard route steps walked', $standardRoute->steps()->count(), $stepCount);

// ========== PHASE 6: Test with Simplified Route ==========
echo "\n--- Phase 6: Quick Test with Simplified Route ---\n";

$simpTask = Task::create([
    'document_category_id' => $category->id,
    'partner_id' => $partner->id,
    'initiator_id' => $initiator->id,
    'assigned_lawyer_id' => $lawyer->id,
    'route_type' => 'simplified',
    'workflow_route_id' => $simplifiedRoute->id,
    'status' => TaskStatus::Draft,
    'current_step' => 0,
]);

$engine->submit($simpTask, $initiator);
$simpTask->refresh();
echo "  Simplified route first step: {$simplifiedRoute->firstStep()->name}\n";
echo "  After submit, status: {$simpTask->status->value}\n";

$stepCount = 0;
while ($simpTask->status !== TaskStatus::Approved && $stepCount < 5) {
    $step = $engine->getCurrentStep($simpTask);
    $actor = match ($step->role) {
        'manager' => $manager,
        'lawyer' => $lawyer,
        'initiator' => $initiator,
        default => $admin,
    };
    $result = $engine->advance($simpTask, $actor, "Simplified step done");
    $simpTask->refresh();
    $stepCount++;
    echo "  Advanced step {$step->sort_order}: status={$simpTask->status->value}\n";
}
assert_eq('Simplified route completed', 'approved', $simpTask->status->value);

// ========== PHASE 7: Frontend-Backend matching checks ==========
echo "\n--- Phase 7: Frontend-Backend API Compatibility ---\n";

// Test the workflow-routes API response format
$apiRoutes = WorkflowRoute::where('is_active', true)
    ->with(['steps' => fn ($q) => $q->orderBy('sort_order')])
    ->orderBy('name')
    ->get()
    ->map(fn ($route) => [
        'id' => $route->id,
        'name' => $route->name,
        'slug' => $route->slug,
        'description' => $route->description,
        'is_default' => $route->is_default,
        'steps' => $route->steps->map(fn ($s) => [
            'id' => $s->id,
            'sort_order' => $s->sort_order,
            'name' => $s->name,
            'role' => $s->role,
            'action_type' => $s->action_type,
        ]),
    ]);

foreach ($apiRoutes as $r) {
    assert_eq("API route '{$r['name']}' has id", true, isset($r['id']));
    assert_eq("API route '{$r['name']}' has slug", true, !empty($r['slug']));
    assert_eq("API route '{$r['name']}' has steps", true, count($r['steps']) > 0);
    foreach ($r['steps'] as $s) {
        assert_eq("  Step '{$s['name']}' has sort_order", true, isset($s['sort_order']));
        assert_eq("  Step '{$s['name']}' has role", true, in_array($s['role'], ['manager', 'lawyer', 'initiator']));
        assert_eq("  Step '{$s['name']}' has action_type", true, in_array($s['action_type'], ['review', 'approve', 'sign', 'submit']));
    }
}

// Test task response includes workflow_route
$task->refresh();
$task->load('workflowRoute.steps');
assert_eq('Task has workflow_route relation', true, $task->workflowRoute !== null);
assert_eq('Task workflow_route has steps', true, $task->workflowRoute->steps->count() > 0);

// Test StepIndicator data alignment
echo "\n--- Phase 7b: StepIndicator Alignment ---\n";
$stepsFromRoute = $task->workflowRoute->steps()->orderBy('sort_order')->get();
echo "  Steps from route:\n";
foreach ($stepsFromRoute as $s) {
    echo "    sort_order={$s->sort_order}, name={$s->name}, role={$s->role}\n";
}
echo "  Task current_step (sort_order): {$task->current_step}\n";
assert_eq('current_step equals last step sort_order (approved)', $lastStep->sort_order, $task->current_step);

// ========== CLEANUP ==========
echo "\n--- Cleanup ---\n";
$task->activities()->delete();
$task->delete();
$stdTask->activities()->delete();
$stdTask->delete();
$simpTask->activities()->delete();
$simpTask->delete();
echo "  Test tasks cleaned up.\n";

// ========== RESULTS ==========
echo "\n=============================\n";
echo "RESULTS: {$pass} passed, {$fail} failed\n";
echo "=============================\n";
exit($fail > 0 ? 1 : 0);
