<?php

namespace App\Services;

use App\Enums\TaskStatus;
use App\Jobs\GenerateTaskPdfJob;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskStepCompletion;
use App\Models\User;
use App\Models\PartnerAccessToken;
use App\Models\WorkflowRoute;
use App\Models\WorkflowStep;
use App\Models\WorkflowTransition;
use App\Notifications\PartnerAccessNotification;
use App\Notifications\TaskStatusNotification;
use Illuminate\Support\Collection;

class WorkflowEngine
{
    // ─── Query helpers ────────────────────────────────────────────────

    public function getStepsForRoute(WorkflowRoute $route): Collection
    {
        return $route->steps()->orderBy('sort_order')->get();
    }

    public function getCurrentStep(Task $task): ?WorkflowStep
    {
        if ($task->current_workflow_step_id) {
            return WorkflowStep::find($task->current_workflow_step_id);
        }

        return null;
    }

    public function getActiveSteps(Task $task): Collection
    {
        return TaskStepCompletion::where('task_id', $task->id)
            ->where('status', 'active')
            ->with('step')
            ->get();
    }

    // ─── Available outcomes for the frontend ──────────────────────────

    public function getAvailableOutcomes(Task $task, ?User $actor = null): array
    {
        if ($this->isComplete($task)) {
            return [];
        }

        $activeCompletions = $this->getActiveSteps($task);
        if ($activeCompletions->isEmpty()) {
            $step = $this->getCurrentStep($task);
            if (! $step) {
                return $this->legacyOutcomes($task);
            }

            if ($actor && ! $this->canActOnStep($step, $actor, $task)) {
                return [];
            }

            return $this->outcomesForStep($step, $task);
        }

        $outcomes = [];
        foreach ($activeCompletions as $completion) {
            if ($completion->step) {
                if ($actor && ! $this->canActOnStep($completion->step, $actor, $task)) {
                    continue;
                }
                $outcomes = array_merge($outcomes, $this->outcomesForStep($completion->step, $task));
            }
        }

        return array_values(array_unique($outcomes));
    }

    private function outcomesForStep(WorkflowStep $step, Task $task): array
    {
        $transitions = WorkflowTransition::where('from_step_id', $step->id)
            ->where('workflow_route_id', $task->workflow_route_id)
            ->orderBy('priority')
            ->get();

        if ($transitions->isEmpty()) {
            return ['approved'];
        }

        $outcomes = [];
        foreach ($transitions as $t) {
            $type = $t->condition['type'] ?? 'always';
            if (in_array($type, ['approved', 'rejected', 'needs_revision'])) {
                $outcomes[] = $type;
            } elseif ($type === 'always') {
                $outcomes[] = 'approved';
            } else {
                $outcomes[] = 'approved';
            }
        }

        if (! in_array('approved', $outcomes)) {
            $outcomes[] = 'approved';
        }

        return array_values(array_unique($outcomes));
    }

    private function legacyOutcomes(Task $task): array
    {
        if ($task->status === TaskStatus::Draft) {
            return [];
        }
        if ($task->status->isPending()) {
            return ['approved', 'rejected'];
        }

        return [];
    }

    // ─── Submit (start workflow) ──────────────────────────────────────

    public function submit(Task $task, User $actor): void
    {
        $route = $task->workflowRoute;
        if (! $route) {
            return;
        }

        $firstStep = $route->firstStep();
        if (! $firstStep) {
            return;
        }

        $task->update([
            'status' => $this->mapRoleToStatus($firstStep->role, false),
            'current_step' => $firstStep->sort_order,
            'current_workflow_step_id' => $firstStep->id,
        ]);

        TaskStepCompletion::create([
            'task_id' => $task->id,
            'workflow_step_id' => $firstStep->id,
            'status' => 'active',
        ]);

        $this->notifyForStep($task, $firstStep);
    }

    // ─── Advance (internal user) ─────────────────────────────────────

    public function advance(Task $task, User $actor, string $outcome = 'approved', ?string $comment = null): array
    {
        $currentStep = $this->resolveCurrentStep($task, $actor);
        if (! $currentStep) {
            return ['success' => false, 'message' => 'No current workflow step found.'];
        }

        if (! $this->canActOnStep($currentStep, $actor, $task)) {
            return ['success' => false, 'message' => 'You do not have permission to act on this step.'];
        }

        return $this->processStepCompletion($task, $currentStep, $outcome, 'user', $actor->id, $comment, $actor);
    }

    // ─── Advance as partner ──────────────────────────────────────────

    public function advanceAsPartner(Task $task, PartnerAccessToken $access, string $outcome = 'approved', ?string $comment = null): array
    {
        $currentStep = $this->getCurrentStep($task);
        if (! $currentStep || $currentStep->id !== $access->workflow_step_id) {
            return ['success' => false, 'message' => 'This step is no longer active.'];
        }

        return $this->processStepCompletion($task, $currentStep, $outcome, 'partner', $access->partner_id, $comment, null, $access);
    }

    // ─── Core: process step completion ───────────────────────────────

    private function processStepCompletion(
        Task $task,
        WorkflowStep $step,
        string $outcome,
        string $actorType,
        ?int $actorId,
        ?string $comment,
        ?User $actor = null,
        ?PartnerAccessToken $access = null,
    ): array {
        $this->markStepCompleted($task, $step, $outcome, $actorType, $actorId, $comment);

        $this->logActivity($task, $step, $outcome, $actorType, $actorId, $comment, $access);

        if ($outcome === 'rejected') {
            return $this->handleRejection($task, $step, $comment, $actor);
        }

        $matchingTransitions = $this->getMatchingTransitions($step, $task, $outcome);

        if ($matchingTransitions->isEmpty()) {
            return $this->handleTerminal($task, $step, $outcome, $actor);
        }

        $targetSteps = $matchingTransitions->map(fn ($t) => $t->toStep)->filter()->unique('id');

        $activatedSteps = [];
        foreach ($targetSteps as $targetStep) {
            if ($this->checkJoinReady($task, $targetStep)) {
                $this->activateStep($task, $targetStep);
                $activatedSteps[] = $targetStep;
            }
        }

        if (! empty($activatedSteps)) {
            $primary = $activatedSteps[0];
            $task->update([
                'status' => $this->mapRoleToStatus($primary->role, $primary->action_type === 'sign'),
                'current_step' => $primary->sort_order,
                'current_workflow_step_id' => $primary->id,
            ]);

            foreach ($activatedSteps as $s) {
                $this->notifyForStep($task, $s);
            }
        }

        return ['success' => true, 'action' => $outcome, 'terminal' => false];
    }

    // ─── Transition evaluation ───────────────────────────────────────

    private function getMatchingTransitions(WorkflowStep $step, Task $task, string $outcome): Collection
    {
        $transitions = WorkflowTransition::where('from_step_id', $step->id)
            ->where('workflow_route_id', $task->workflow_route_id)
            ->orderBy('priority')
            ->get();

        return $transitions->filter(fn (WorkflowTransition $t) => $t->evaluateFor($task, $outcome));
    }

    // ─── Parallel join check ─────────────────────────────────────────

    private function checkJoinReady(Task $task, WorkflowStep $targetStep): bool
    {
        $incomingTransitions = WorkflowTransition::where('to_step_id', $targetStep->id)
            ->where('workflow_route_id', $task->workflow_route_id)
            ->get();

        if ($incomingTransitions->count() <= 1) {
            return true;
        }

        $requiredFromStepIds = $incomingTransitions->pluck('from_step_id')->unique();
        $activatedCount = 0;

        foreach ($requiredFromStepIds as $fromStepId) {
            $latestCompletion = TaskStepCompletion::where('task_id', $task->id)
                ->where('workflow_step_id', $fromStepId)
                ->latest('id')
                ->first();

            if (! $latestCompletion) {
                continue;
            }

            $activatedCount++;

            if ($latestCompletion->status === 'active') {
                return false;
            }

            if ($latestCompletion->status === 'completed' && $latestCompletion->outcome === 'rejected') {
                return false;
            }
        }

        return $activatedCount > 0;
    }

    // ─── Step activation ─────────────────────────────────────────────

    private function activateStep(Task $task, WorkflowStep $step): TaskStepCompletion
    {
        $existing = TaskStepCompletion::where('task_id', $task->id)
            ->where('workflow_step_id', $step->id)
            ->where('status', 'active')
            ->first();

        if ($existing) {
            return $existing;
        }

        return TaskStepCompletion::create([
            'task_id' => $task->id,
            'workflow_step_id' => $step->id,
            'status' => 'active',
        ]);
    }

    private function markStepCompleted(
        Task $task,
        WorkflowStep $step,
        string $outcome,
        string $actorType,
        ?int $actorId,
        ?string $comment,
    ): void {
        $completion = TaskStepCompletion::where('task_id', $task->id)
            ->where('workflow_step_id', $step->id)
            ->where('status', 'active')
            ->first();

        if ($completion) {
            $completion->update([
                'status' => 'completed',
                'outcome' => $outcome,
                'actor_type' => $actorType,
                'actor_id' => $actorId,
                'comment' => $comment,
                'completed_at' => now(),
            ]);
        } else {
            TaskStepCompletion::create([
                'task_id' => $task->id,
                'workflow_step_id' => $step->id,
                'status' => 'completed',
                'outcome' => $outcome,
                'actor_type' => $actorType,
                'actor_id' => $actorId,
                'comment' => $comment,
                'completed_at' => now(),
            ]);
        }
    }

    // ─── Rejection handling ──────────────────────────────────────────

    private function handleRejection(Task $task, WorkflowStep $step, ?string $comment, ?User $actor): array
    {
        $rejectionTransitions = $this->getMatchingTransitions($step, $task, 'rejected');

        if ($rejectionTransitions->isNotEmpty()) {
            $target = $rejectionTransitions->first()->toStep;
            if ($target) {
                return $this->sendToStep($task, $target, 'rejected');
            }
        }

        $initiatorStep = $this->findInitiatorStep($task);
        if ($initiatorStep) {
            return $this->sendToStep($task, $initiatorStep, 'needs_revision');
        }

        $this->skipActiveSteps($task);
        $task->update(['status' => TaskStatus::Rejected]);

        try {
            $task->load('initiator');
            if ($task->initiator) {
                $task->initiator->notify(new TaskStatusNotification(
                    'Task #'.$task->id.' has been rejected.',
                    $task->id,
                    'rejected'
                ));
            }
        } catch (\Throwable $e) {
            \Log::warning('Rejection notification failed for task #'.$task->id.': '.$e->getMessage());
        }

        return ['success' => true, 'action' => 'rejected', 'terminal' => true];
    }

    private function sendToStep(Task $task, WorkflowStep $target, string $action): array
    {
        $this->skipActiveSteps($task);
        $this->activateStep($task, $target);

        $status = $action === 'needs_revision'
            ? TaskStatus::NeedsRevision
            : $this->mapRoleToStatus($target->role, $target->action_type === 'sign');

        $task->update([
            'status' => $status,
            'current_step' => $target->sort_order,
            'current_workflow_step_id' => $target->id,
        ]);

        $this->notifyForStep($task, $target);

        return ['success' => true, 'action' => $action, 'terminal' => false];
    }

    private function findInitiatorStep(Task $task): ?WorkflowStep
    {
        $initiatorStep = WorkflowStep::where('workflow_route_id', $task->workflow_route_id)
            ->where('role', 'initiator')
            ->orderBy('sort_order')
            ->first();

        if ($initiatorStep) {
            return $initiatorStep;
        }

        return WorkflowStep::where('workflow_route_id', $task->workflow_route_id)
            ->orderBy('sort_order')
            ->first();
    }

    private function skipActiveSteps(Task $task): void
    {
        TaskStepCompletion::where('task_id', $task->id)
            ->where('status', 'active')
            ->update(['status' => 'skipped', 'completed_at' => now()]);
    }

    // ─── Terminal step (no outgoing transitions) ─────────────────────

    private function handleTerminal(Task $task, WorkflowStep $step, string $outcome, ?User $actor): array
    {
        $task->update([
            'status' => TaskStatus::Approved,
            'current_step' => $step->sort_order,
        ]);

        try {
            GenerateTaskPdfJob::dispatch($task);
        } catch (\Throwable $e) {
            \Log::warning('GenerateTaskPdfJob failed for task #'.$task->id.': '.$e->getMessage());
        }

        try {
            $latestDoc = $task->documents()->orderByDesc('version')->first();
            if ($latestDoc) {
                $absPath = \Illuminate\Support\Facades\Storage::disk('local')->path($latestDoc->path);
                if (file_exists($absPath) && str_ends_with(strtolower($absPath), '.pdf')) {
                    $protector = app(\App\Services\PdfProtector::class);
                    $protector->protect($absPath, $task->registration_number ?? '');
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('PDF protection failed for task #'.$task->id.': '.$e->getMessage());
        }

        try {
            $task->load('initiator');
            if ($task->initiator) {
                $task->initiator->notify(new TaskStatusNotification(
                    'Task #'.$task->id.' has been fully approved.',
                    $task->id,
                    'approved'
                ));
            }
        } catch (\Throwable $e) {
            \Log::warning('Approval notification failed for task #'.$task->id.': '.$e->getMessage());
        }

        try {
            $sapService = app(\App\Services\SapService::class);
            if ($sapService->isEnabled()) {
                $sapService->createDocument($task);
            }
        } catch (\Throwable $e) {
            \Log::warning('SAP document sync failed for task #'.$task->id.': '.$e->getMessage());
        }

        return ['success' => true, 'action' => 'approved', 'terminal' => true];
    }

    // ─── Activity logging ────────────────────────────────────────────

    private function logActivity(
        Task $task,
        WorkflowStep $step,
        string $outcome,
        string $actorType,
        ?int $actorId,
        ?string $comment,
        ?PartnerAccessToken $access = null,
    ): void {
        $actionMap = [
            'approved' => $step->action_type === 'sign' ? 'signed' : 'approved',
            'rejected' => 'rejected',
            'needs_revision' => 'returned_for_revision',
        ];

        $meta = null;
        if ($actorType === 'partner' && $access) {
            $actionMap['approved'] = $step->action_type === 'sign' ? 'partner_signed' : 'partner_approved';
            $actionMap['rejected'] = 'partner_rejected';
            $meta = ['partner_id' => $access->partner_id, 'partner_name' => $access->partner->name ?? ''];
        }

        TaskActivity::create([
            'task_id' => $task->id,
            'user_id' => $actorType === 'user' ? $actorId : null,
            'action' => $actionMap[$outcome] ?? $outcome,
            'comment' => $comment,
            'meta' => $meta,
        ]);
    }

    // ─── Resolve which step the actor is acting on ───────────────────

    private function resolveCurrentStep(Task $task, User $actor): ?WorkflowStep
    {
        $activeCompletions = $this->getActiveSteps($task);

        if ($activeCompletions->isNotEmpty()) {
            foreach ($activeCompletions as $completion) {
                if ($completion->step && $this->canActOnStep($completion->step, $actor, $task)) {
                    return $completion->step;
                }
            }
        }

        return $this->getCurrentStep($task);
    }

    // ─── Permission check ────────────────────────────────────────────

    public function isComplete(Task $task): bool
    {
        return in_array($task->status, [TaskStatus::Approved, TaskStatus::Archived, TaskStatus::Rejected]);
    }

    public function canActOnStep(WorkflowStep $step, User $actor, ?Task $task = null): bool
    {
        if ($actor->isAdmin()) {
            return true;
        }

        $directMatch = match ($step->role) {
            'manager' => $actor->isManager(),
            'lawyer' => $actor->isLawyer(),
            'initiator' => $task ? $task->initiator_id === $actor->id : true,
            'partner' => $task ? $task->initiator_id === $actor->id || $actor->isManager() : true,
            'gm' => $actor->role->value === 'gm' || $actor->isAdmin(),
            default => false,
        };

        if ($directMatch) {
            return true;
        }

        $originalUserIds = \App\Models\Substitution::getOriginalUsersFor($actor->id);
        if (empty($originalUserIds)) {
            return false;
        }

        foreach ($originalUserIds as $originalUserId) {
            $originalUser = User::find($originalUserId);
            if (! $originalUser) continue;
            
            $originalCanAct = match ($step->role) {
                'manager' => $originalUser->isManager(),
                'lawyer' => $originalUser->isLawyer(),
                'initiator' => $task ? $task->initiator_id === $originalUser->id : true,
                'gm' => $originalUser->role->value === 'gm' || $originalUser->isAdmin(),
                default => false,
            };

            if ($originalCanAct) {
                return true;
            }
        }

        return false;
    }

    public function mapRoleToStatus(string $role, bool $isSignStep = false): TaskStatus
    {
        if ($isSignStep && $role === 'initiator') {
            return TaskStatus::PendingInitiator;
        }

        return match ($role) {
            'manager' => TaskStatus::PendingManager,
            'lawyer' => TaskStatus::PendingLawyer,
            'initiator' => TaskStatus::PendingInitiator,
            'partner' => TaskStatus::PendingPartner,
            'gm' => TaskStatus::PendingGM,
            default => TaskStatus::PendingManager,
        };
    }

    // ─── Notifications ───────────────────────────────────────────────

    private function notifyForStep(Task $task, WorkflowStep $step): void
    {
        $isRevision = $task->status === TaskStatus::NeedsRevision;
        $message = $isRevision
            ? "Task #{$task->id} returned for revision (partner rejected). Please revise and re-submit."
            : "Task #{$task->id} requires action: {$step->name}";
        $type = $isRevision ? 'needs_revision' : 'pending';

        match ($step->role) {
            'manager' => $this->notifyManagers($task, $message, $type),
            'lawyer' => $this->notifyLawyer($task, $message, $type),
            'initiator' => $task->initiator->notify(new TaskStatusNotification($message, $task->id, $type)),
            'partner' => $this->notifyPartnerStakeholders($task, $message, $type),
            'gm' => $this->notifyGm($task, $message, $type),
            default => null,
        };
    }

    private function notifyManagers(Task $task, string $message, string $type = 'pending'): void
    {
        User::where('role', 'manager')->each(function (User $manager) use ($task, $message, $type) {
            $manager->notify(new TaskStatusNotification($message, $task->id, $type));
            $this->notifySubstitutes($manager->id, $task, $message, $type);
        });
    }

    private function notifyPartnerStakeholders(Task $task, string $message, string $type = 'pending'): void
    {
        $task->initiator->notify(new TaskStatusNotification($message, $task->id, $type));
        $this->notifyManagers($task, $message, $type);

        $currentStep = $this->getCurrentStep($task);
        if ($currentStep && $task->partner && $task->partner->email) {
            $token = PartnerAccessToken::generateForTask($task, $currentStep);
            if ($token) {
                $frontendUrl = rtrim(config('app.frontend_url', config('app.url')), '/');
                $accessUrl = $frontendUrl.'/partner/'.$token->token;
                \Illuminate\Support\Facades\Notification::route('mail', $task->partner->email)
                    ->notify(new PartnerAccessNotification($task, $currentStep, $accessUrl, $token));
            }
        }
    }

    private function notifyLawyer(Task $task, string $message, string $type = 'pending'): void
    {
        if ($task->assigned_lawyer_id) {
            $task->assignedLawyer->notify(new TaskStatusNotification($message, $task->id, $type));
            $this->notifySubstitutes($task->assigned_lawyer_id, $task, $message, $type);
        } else {
            User::where('role', 'lawyer')->each(function (User $lawyer) use ($task, $message, $type) {
                $lawyer->notify(new TaskStatusNotification($message, $task->id, $type));
                $this->notifySubstitutes($lawyer->id, $task, $message, $type);
            });
        }
    }

    private function notifyGm(Task $task, string $message, string $type = 'pending'): void
    {
        $gmUserId = \App\Models\Setting::get('gm_user_id');
        if ($gmUserId) {
            $gm = User::find($gmUserId);
            if ($gm) {
                $gm->notify(new TaskStatusNotification($message, $task->id, $type));
                $this->notifySubstitutes($gm->id, $task, $message, $type);
            }
        }
    }

    private function notifySubstitutes(int $userId, Task $task, string $message, string $type): void
    {
        $substituteIds = \App\Models\Substitution::getSubstitutesFor($userId);
        foreach ($substituteIds as $subId) {
            $sub = User::find($subId);
            if ($sub) {
                $sub->notify(new TaskStatusNotification($message, $task->id, $type));
            }
        }
    }

    // ─── Legacy compatibility: getNextStep still works for simple flows ─

    public function getNextStep(Task $task, string $outcome = 'approved'): ?WorkflowStep
    {
        $current = $this->getCurrentStep($task);
        if (! $current) {
            return null;
        }

        $matching = $this->getMatchingTransitions($current, $task, $outcome);

        return $matching->isNotEmpty() ? $matching->first()->toStep : null;
    }
}
