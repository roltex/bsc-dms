<?php

namespace App\Http\Controllers\Api;

use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Jobs\GenerateTaskPdfJob;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskDocument;
use App\Models\User;
use App\Models\WorkflowRoute;
use App\Notifications\TaskStatusNotification;
use App\Services\TaskMainDocumentCommentMigrationService;
use App\Services\TemplateDocumentGenerator;
use App\Services\WorkflowEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TaskController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Task::query()
            ->with(['category', 'partner', 'initiator', 'assignedLawyer', 'workflowRoute']);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('document_category_id')) {
            $query->where('document_category_id', $request->input('document_category_id'));
        }

        if ($request->filled('search')) {
            $s = trim((string) $request->input('search'));
            $query->where(function ($q) use ($s) {
                if (ctype_digit($s)) {
                    $q->orWhere('id', (int) $s);
                }
                $q->orWhere('registration_number', 'like', "%{$s}%")
                  ->orWhere('commercial_terms', 'like', "%{$s}%")
                  ->orWhereHas('partner', fn ($pq) => $pq->where('name', 'like', "%{$s}%")
                      ->orWhere('bin_iin', 'like', "%{$s}%"))
                  ->orWhereHas('initiator', fn ($iq) => $iq->where('name', 'like', "%{$s}%"));
            });
        }

        if ($request->filled('partner_id')) {
            $query->where('partner_id', $request->integer('partner_id'));
        }

        if ($request->filled('initiator_id')) {
            $query->where('initiator_id', $request->integer('initiator_id'));
        }

        if ($request->filled('assigned_lawyer_id')) {
            $query->where('assigned_lawyer_id', $request->integer('assigned_lawyer_id'));
        }

        if ($request->filled('route_type')) {
            $query->where('route_type', $request->input('route_type'));
        }

        if ($request->filled('workflow_route_id')) {
            $query->where('workflow_route_id', $request->integer('workflow_route_id'));
        }

        if ($request->filled('fast_tracked')) {
            $query->where('fast_tracked', filter_var($request->input('fast_tracked'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('deadline_from')) {
            try {
                $query->whereDate('deadline', '>=', \Carbon\Carbon::parse($request->input('deadline_from'))->toDateString());
            } catch (\Throwable) {
                // ignore
            }
        }

        if ($request->filled('deadline_to')) {
            try {
                $query->whereDate('deadline', '<=', \Carbon\Carbon::parse($request->input('deadline_to'))->toDateString());
            } catch (\Throwable) {
                // ignore
            }
        }

        if ($request->filled('created_from')) {
            try {
                $query->whereDate('created_at', '>=', \Carbon\Carbon::parse($request->input('created_from'))->toDateString());
            } catch (\Throwable) {
                // ignore
            }
        }

        if ($request->filled('created_to')) {
            try {
                $query->whereDate('created_at', '<=', \Carbon\Carbon::parse($request->input('created_to'))->toDateString());
            } catch (\Throwable) {
                // ignore
            }
        }

        if ($request->filled('min_amount')) {
            $query->where('amount', '>=', (float) $request->input('min_amount'));
        }

        if ($request->filled('max_amount')) {
            $query->where('amount', '<=', (float) $request->input('max_amount'));
        }

        if ($request->boolean('overdue')) {
            $query->whereNotNull('deadline')
                ->where('deadline', '<', now())
                ->whereNotIn('status', [
                    TaskStatus::Draft,
                    TaskStatus::Approved,
                    TaskStatus::Archived,
                    TaskStatus::Rejected,
                ]);
        }

        if ($user->isInitiator()) {
            $query->where('initiator_id', $user->id);
        } elseif ($user->isManager()) {
            $query->where(function ($q) use ($user) {
                $q->whereIn('status', [
                    TaskStatus::PendingManager,
                    TaskStatus::PendingFinalManager,
                    TaskStatus::Approved,
                    TaskStatus::Archived,
                ])->orWhere('initiator_id', $user->id);
            });
        } elseif ($user->isLawyer()) {
            $query->where(function ($q) use ($user) {
                $q->whereIn('status', [
                    TaskStatus::PendingLawyer,
                    TaskStatus::PendingFinalLawyer,
                ])->orWhere('assigned_lawyer_id', $user->id);
            });
        }

        $sortField = $request->input('sort', 'updated_at');
        $sortDir = $request->input('dir', 'desc');
        $allowedSort = ['created_at', 'updated_at', 'deadline', 'id', 'amount'];
        if (in_array($sortField, $allowedSort, true)) {
            $query->orderBy($sortField, $sortDir === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderByDesc('updated_at');
        }

        $tasks = $query->paginate($request->integer('per_page', 15));

        return response()->json($tasks);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'document_category_id' => 'required|exists:document_categories,id',
            'partner_id' => 'nullable|exists:partners,id',
            'route_type' => 'nullable|string',
            'workflow_route_id' => 'nullable|exists:workflow_routes,id',
            'commercial_terms' => 'nullable|string|max:5000',
            'amount' => 'nullable|numeric|min:0',
            'validity_from' => 'nullable|date',
            'validity_to' => 'nullable|date|after_or_equal:validity_from',
            'deadline' => 'required|date|after:today',
            'template_id' => 'nullable|exists:document_templates,id',
            'extra_variables' => 'nullable|array',
            'extra_variables.*' => 'nullable|string|max:5000',
            'table_data' => 'nullable|array',
            'step_durations' => 'nullable|array',
            'step_durations.*' => 'integer|min:0|max:365',
            'document_html' => 'nullable|string',
            'google_file_id' => 'nullable|string',
            'document' => 'nullable|file|max:20480',
            'documents' => 'nullable|array',
            'documents.*' => 'nullable|file|max:20480',
        ]);

        $stepDurations = $request->input('step_durations');
        $stepDurations = is_array($stepDurations) ? $stepDurations : null;

        if (! empty($stepDurations) && array_sum($stepDurations) > 0) {
            $totalDays = (int) array_sum($stepDurations);
            $minDeadline = now()->startOfDay()->addDays($totalDays);
            $deadlineCarbon = \Carbon\Carbon::parse($validated['deadline']);
            if ($deadlineCarbon->lt($minDeadline)) {
                return response()->json([
                    'message' => 'The deadline must be on or after '.$minDeadline->toDateString().' (sum of step durations is '.$totalDays.' days).',
                    'errors' => [
                        'deadline' => ['Deadline is earlier than the sum of step durations ('.$totalDays.' days).'],
                    ],
                ], 422);
            }
        }
        $validated['step_durations'] = $stepDurations;

        $category = \App\Models\DocumentCategory::find($validated['document_category_id']);

        $routeType = $validated['route_type'] ?? 'standard';
        $workflowRouteId = $validated['workflow_route_id'] ?? null;

        if ($workflowRouteId) {
            $wfRoute = WorkflowRoute::find($workflowRouteId);
            if ($wfRoute) {
                $routeType = $wfRoute->slug;
            }
        } elseif (! $workflowRouteId) {
            $wfRoute = WorkflowRoute::where('slug', $routeType)->where('is_active', true)->first();
            $workflowRouteId = $wfRoute?->id;
        }

        $task = Task::create([
            ...$validated,
            'initiator_id' => $request->user()->id,
            'status' => TaskStatus::Draft,
            'route_type' => $routeType,
            'workflow_route_id' => $workflowRouteId,
            'assigned_lawyer_id' => $category->default_lawyer_id,
        ]);

        if (! $task->registration_number) {
            $prefix = \App\Models\Setting::get('registration_number_prefix', 'DOC');
            $year = date('Y');
            $seq = str_pad($task->id, 4, '0', STR_PAD_LEFT);
            $catCode = strtoupper(substr($category->name ?? 'DOC', 0, 3));
            $task->update(['registration_number' => "{$prefix}-{$catCode}-{$year}-{$seq}"]);
        }

        TaskActivity::create([
            'task_id' => $task->id,
            'user_id' => $request->user()->id,
            'action' => 'created',
        ]);

        if ($request->hasFile('documents')) {
            foreach ($request->file('documents') as $uploadedFile) {
                $doc = TaskDocument::storeUpload($task, $uploadedFile, 1, true);
                TaskActivity::create([
                    'task_id' => $task->id,
                    'user_id' => $request->user()->id,
                    'action' => 'attachment_uploaded',
                    'meta' => ['document_id' => $doc->id, 'version' => 1, 'filename' => $uploadedFile->getClientOriginalName()],
                ]);
            }
        } elseif ($request->hasFile('document')) {
            $doc = TaskDocument::storeUpload($task, $request->file('document'), 1);
            app(TaskMainDocumentCommentMigrationService::class)->apply($task, $doc);
            TaskActivity::create([
                'task_id' => $task->id,
                'user_id' => $request->user()->id,
                'action' => 'document_uploaded',
                'meta' => ['document_id' => $doc->id, 'version' => 1],
            ]);
        } elseif ($request->filled('template_id')) {
            $template = \App\Models\DocumentTemplate::find($request->input('template_id'));
            if ($template && $template->path) {
                $generator = app(TemplateDocumentGenerator::class);
                $extraVars = $request->input('extra_variables', []);
                $tableData = $request->input('table_data', []);
                $doc = $generator->generate(
                    $template,
                    $task,
                    is_array($extraVars) ? $extraVars : [],
                    is_array($tableData) ? $tableData : [],
                );
                TaskActivity::create([
                    'task_id' => $task->id,
                    'user_id' => $request->user()->id,
                    'action' => 'document_generated',
                    'meta' => ['document_id' => $doc->id, 'template_id' => $template->id, 'template_name' => $template->name],
                ]);
                app(TaskMainDocumentCommentMigrationService::class)->apply($task, $doc);
            }
        }

        if ($request->filled('document_html')) {
            try {
                $contentService = app(\App\Services\DocumentContentService::class);
                $contentService->saveHtml($task, $request->input('document_html'), $request->user());
            } catch (\Throwable $e) {
                \Log::warning('Could not apply editor HTML during task creation', ['error' => $e->getMessage()]);
            }
        }

        if ($request->filled('google_file_id')) {
            try {
                $drive = app(\App\Services\GoogleDriveService::class);
                if ($drive->isConfigured()) {
                    $googleFileId = $request->input('google_file_id');
                    $tempDocx = tempnam(sys_get_temp_dir(), 'gcreate_') . '.docx';
                    $drive->downloadDocx($googleFileId, $tempDocx);

                    $generator = app(TemplateDocumentGenerator::class);
                    $template = $request->filled('template_id')
                        ? \App\Models\DocumentTemplate::find($request->input('template_id'))
                        : null;
                    $generator->replaceVariablesInDocxForTask(
                        $tempDocx,
                        $task,
                        $request->input('extra_variables', []),
                        $request->input('table_data', []),
                        $template,
                    );

                    $lastVersion = $task->documents()->max('version') ?? 0;
                    $storagePath = 'tasks/'.$task->id.'/google-edited-v'.($lastVersion + 1).'.docx';
                    Storage::disk('local')->put($storagePath, file_get_contents($tempDocx));

                    $pdfStoragePath = null;
                    try {
                        $converter = app(\App\Services\DocToPdfConverter::class);
                        $absDocx = Storage::disk('local')->path($storagePath);
                        $pdfAbsPath = preg_replace('/\.docx$/i', '.pdf', $absDocx);
                        $converter->convertFromAbsPath($absDocx, $pdfAbsPath);
                        if (file_exists($pdfAbsPath)) {
                            $pdfStoragePath = preg_replace('/\.docx$/i', '.pdf', $storagePath);
                        }
                    } catch (\Throwable) {
                    }

                    $docPath = $pdfStoragePath ?? $storagePath;
                    $docMime = $pdfStoragePath
                        ? 'application/pdf'
                        : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

                    $googleDoc = $task->documents()->create([
                        'path' => $docPath,
                        'mime_type' => $docMime,
                        'version' => $lastVersion + 1,
                    ]);
                    app(TaskMainDocumentCommentMigrationService::class)->apply($task, $googleDoc);

                    $drive->deleteFile($googleFileId);
                    @unlink($tempDocx);
                }
            } catch (\Throwable $e) {
                \Log::warning('Could not sync Google Docs during task creation', ['error' => $e->getMessage()]);
            }
        }

        $task->load(['category', 'partner', 'initiator', 'documents', 'activities.user', 'assignedLawyer', 'reviewers']);

        return response()->json($task, 201);
    }

    public function show(Request $request, Task $task): JsonResponse
    {
        $task->load([
            'category',
            'partner',
            'initiator',
            'assignedLawyer',
            'documents.signer',
            'activities.user',
            'reviewers',
            'workflowRoute.steps',
            'comments' => fn ($q) => $q->whereNull('parent_id')->whereNull('page')->with(['user:id,name', 'replies.user:id,name'])->orderByDesc('created_at'),
        ]);

        $data = $task->toArray();

        $activeToken = \App\Models\PartnerAccessToken::where('task_id', $task->id)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if ($activeToken) {
            $frontendUrl = rtrim(config('app.frontend_url', config('app.url')), '/');
            $data['partner_access'] = [
                'url' => $frontendUrl.'/partner/'.$activeToken->token,
                'email' => $activeToken->partner_email,
                'expires_at' => $activeToken->expires_at->toIso8601String(),
                'step_name' => $activeToken->workflowStep?->name,
            ];
        }

        $engine = app(WorkflowEngine::class);
        $data['available_actions'] = $engine->getAvailableOutcomes($task, $request->user());
        $data['active_steps'] = $engine->getActiveSteps($task)->map(fn ($c) => [
            'step_id' => $c->workflow_step_id,
            'step_name' => $c->step?->name,
            'role' => $c->step?->role,
            'action_type' => $c->step?->action_type,
            'status' => $c->status,
            'started_at' => optional($c->created_at)?->toIso8601String(),
        ])->values()->toArray();

        $currentStep = $task->workflowRoute
            ? $task->workflowRoute->steps()->where('sort_order', $task->current_step)->first()
            : null;
        $data['current_step_action_type'] = $currentStep?->action_type ?? 'review';
        $data['current_step_name'] = $currentStep?->name;
        $data['current_step_role'] = $currentStep?->role;
        $data['can_edit_attachments'] = (bool) ($currentStep?->config['can_edit_attachments'] ?? false);

        return response()->json($data);
    }

    public function update(Request $request, Task $task): JsonResponse
    {
        if ($task->status !== TaskStatus::Draft) {
            return response()->json(['message' => 'Only draft tasks can be edited.'], 422);
        }
        if ($task->initiator_id !== $request->user()->id) {
            return response()->json(['message' => 'Only initiator can edit.'], 403);
        }

        $validated = $request->validate([
            'document_category_id' => 'sometimes|exists:document_categories,id',
            'partner_id' => 'sometimes|exists:partners,id',
            'route_type' => 'sometimes|nullable|string',
            'commercial_terms' => 'nullable|string',
            'amount' => 'nullable|numeric|min:0',
            'validity_from' => 'nullable|date',
            'validity_to' => 'nullable|date|after_or_equal:validity_from',
            'deadline' => 'nullable|date|after:today',
        ]);

        $task->update($validated);

        TaskActivity::create([
            'task_id' => $task->id,
            'user_id' => $request->user()->id,
            'action' => 'updated',
        ]);

        return response()->json($task->fresh(['category', 'partner', 'initiator', 'documents', 'activities.user', 'reviewers']));
    }

    public function submit(Request $request, Task $task): JsonResponse
    {
        if ($task->status !== TaskStatus::Draft) {
            return response()->json(['message' => 'Task is not in draft.'], 422);
        }
        if ($task->initiator_id !== $request->user()->id) {
            return response()->json(['message' => 'Only initiator can submit.'], 403);
        }

        if ($task->workflow_route_id) {
            $engine = app(WorkflowEngine::class);
            $engine->submit($task, $request->user());
        } else {
            $task->update([
                'status' => TaskStatus::PendingManager,
                'current_step' => 1,
            ]);
            $this->notifyManagers($task, 'New task #'.$task->id.' requires your approval.');
        }

        TaskActivity::create([
            'task_id' => $task->id,
            'user_id' => $request->user()->id,
            'action' => 'submitted',
        ]);

        return response()->json($task->fresh($this->eagerLoads()));
    }

    /**
     * Standard route (6 steps):
     *   1. PendingManager   -> Manager approves -> PendingLawyer
     *   2. PendingLawyer    -> Lawyer approves  -> PendingInitiator
     *   3. PendingInitiator -> Initiator re-submits signed version -> PendingFinalLawyer
     *   4. PendingFinalLawyer -> Lawyer final review -> PendingFinalManager
     *   5. PendingFinalManager -> Manager final approval -> Approved
     *
     * Simplified route (2 steps):
     *   1. PendingManager -> Manager approves -> Approved
     */
    public function approve(Request $request, Task $task): JsonResponse
    {
        $user = $request->user();
        $comment = $request->input('comment', '');

        if ($task->workflow_route_id && $task->current_workflow_step_id) {
            $engine = app(WorkflowEngine::class);
            $result = $engine->advance($task, $user, 'approved', $comment);
            if (! $result['success']) {
                return response()->json(['message' => $result['message']], 422);
            }
            return response()->json($this->taskWithActions($task));
        }

        $result = match (true) {
            $task->status === TaskStatus::PendingManager && ($user->isManager() || $user->isAdmin()) => $this->approveByManager($task, $user),
            $task->status === TaskStatus::PendingLawyer && ($user->isLawyer() || $user->isAdmin()) => $this->approveByLawyer($task, $user),
            $task->status === TaskStatus::PendingInitiator && $task->initiator_id === $user->id => $this->resubmitByInitiator($task, $user, $request),
            $task->status === TaskStatus::PendingFinalLawyer && ($user->isLawyer() || $user->isAdmin()) => $this->approveByFinalLawyer($task, $user),
            $task->status === TaskStatus::PendingFinalManager && ($user->isManager() || $user->isAdmin()) => $this->approveByFinalManager($task, $user),
            default => null,
        };

        if ($result === null) {
            return response()->json(['message' => 'Cannot approve at this stage.'], 422);
        }

        TaskActivity::create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'action' => $result['action'],
            'comment' => $comment,
        ]);

        return response()->json($task->fresh($this->eagerLoads()));
    }

    public function reject(Request $request, Task $task): JsonResponse
    {
        $request->validate(['comment' => 'required|string|max:1000']);

        $user = $request->user();
        $comment = $request->input('comment');

        if ($task->workflow_route_id && $task->current_workflow_step_id) {
            $engine = app(WorkflowEngine::class);
            $result = $engine->advance($task, $user, 'rejected', $comment);
            if (! $result['success']) {
                return response()->json(['message' => $result['message']], 422);
            }
            return response()->json($this->taskWithActions($task));
        }

        $allowed = match (true) {
            $task->status === TaskStatus::PendingManager && ($user->isManager() || $user->isAdmin()) => true,
            $task->status === TaskStatus::PendingFinalManager && ($user->isManager() || $user->isAdmin()) => true,
            in_array($task->status, [TaskStatus::PendingLawyer, TaskStatus::PendingFinalLawyer]) && ($user->isLawyer() || $user->isAdmin()) => true,
            default => false,
        };

        if (! $allowed) {
            return response()->json(['message' => 'Cannot reject at this stage.'], 422);
        }

        $task->update(['status' => TaskStatus::Rejected]);

        TaskActivity::create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'action' => 'rejected',
            'comment' => $comment,
        ]);

        $task->initiator->notify(new TaskStatusNotification(
            'Task #'.$task->id.' has been rejected: '.$comment,
            $task->id,
            'rejected'
        ));

        return response()->json($this->taskWithActions($task));
    }

    public function returnForRevision(Request $request, Task $task): JsonResponse
    {
        $request->validate(['comment' => 'nullable|string|max:1000']);

        $user = $request->user();
        $comment = $request->input('comment', '');

        if ($task->workflow_route_id && $task->current_workflow_step_id) {
            $engine = app(WorkflowEngine::class);
            $result = $engine->advance($task, $user, 'needs_revision', $comment);
            if (! $result['success']) {
                return response()->json(['message' => $result['message']], 422);
            }
            return response()->json($this->taskWithActions($task));
        }

        return response()->json(['message' => 'Return for revision is only available for workflow tasks.'], 422);
    }

    public function availableActions(Request $request, Task $task): JsonResponse
    {
        $engine = app(WorkflowEngine::class);
        $outcomes = $engine->getAvailableOutcomes($task, $request->user());
        $activeSteps = $engine->getActiveSteps($task)->map(fn ($c) => [
            'step_id' => $c->workflow_step_id,
            'step_name' => $c->step?->name,
            'role' => $c->step?->role,
            'status' => $c->status,
        ]);

        return response()->json([
            'available_actions' => $outcomes,
            'active_steps' => $activeSteps,
        ]);
    }

    public function delegate(Request $request, Task $task): JsonResponse
    {
        $request->validate([
            'lawyer_id' => 'required|exists:users,id',
            'comment' => 'nullable|string|max:1000',
        ]);

        $user = $request->user();
        if (! ($user->isLawyer() || $user->isAdmin())) {
            return response()->json(['message' => 'Only lawyers can delegate.'], 403);
        }

        if (! in_array($task->status, [TaskStatus::PendingLawyer, TaskStatus::PendingFinalLawyer])) {
            return response()->json(['message' => 'Task is not pending lawyer review.'], 422);
        }

        $newLawyer = User::findOrFail($request->input('lawyer_id'));
        if (! ($newLawyer->isLawyer() || $newLawyer->isAdmin())) {
            return response()->json(['message' => 'Target user must be a lawyer.'], 422);
        }

        $task->update(['assigned_lawyer_id' => $newLawyer->id]);

        TaskActivity::create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'action' => 'delegated',
            'comment' => $request->input('comment', ''),
            'meta' => ['delegated_to' => $newLawyer->id, 'delegated_to_name' => $newLawyer->name],
        ]);

        $newLawyer->notify(new TaskStatusNotification(
            'Task #'.$task->id.' has been delegated to you for review.',
            $task->id,
            'delegated'
        ));

        return response()->json($task->fresh($this->eagerLoads()));
    }

    public function fastTrack(Request $request, Task $task): JsonResponse
    {
        $user = $request->user();
        if (! ($user->isLawyer() || $user->isAdmin())) {
            return response()->json(['message' => 'Only lawyers can fast-track.'], 403);
        }

        if (! in_array($task->status, [TaskStatus::PendingLawyer, TaskStatus::PendingFinalLawyer])) {
            return response()->json(['message' => 'Task is not pending lawyer review.'], 422);
        }

        $task->update([
            'status' => TaskStatus::Approved,
            'fast_tracked' => true,
            'current_step' => $task->totalSteps(),
        ]);

        GenerateTaskPdfJob::dispatch($task);

        TaskActivity::create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'action' => 'fast_tracked',
            'comment' => $request->input('comment', 'Fast-tracked by lawyer'),
        ]);

        $task->initiator->notify(new TaskStatusNotification(
            'Task #'.$task->id.' has been fast-tracked and approved.',
            $task->id,
            'approved'
        ));

        return response()->json($task->fresh($this->eagerLoads()));
    }

    public function addReviewer(Request $request, Task $task): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'comment' => 'nullable|string|max:1000',
            'deadline' => 'nullable|date|after:now',
        ]);

        $user = $request->user();
        if (! ($user->isLawyer() || $user->isAdmin())) {
            return response()->json(['message' => 'Only lawyers can add reviewers.'], 403);
        }

        $reviewer = User::findOrFail($request->input('user_id'));
        if ($task->reviewers()->where('user_id', $reviewer->id)->exists()) {
            return response()->json(['message' => 'User is already a reviewer.'], 422);
        }

        $deadline = $request->input('deadline');
        $task->reviewers()->attach($reviewer->id, [
            'deadline' => $deadline ? \Carbon\Carbon::parse($deadline) : null,
        ]);

        TaskActivity::create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'action' => 'reviewer_added',
            'meta' => [
                'reviewer_id' => $reviewer->id,
                'reviewer_name' => $reviewer->name,
                'deadline' => $deadline,
            ],
        ]);

        $message = 'You have been added as a reviewer for Task #'.$task->id.'.';
        if ($deadline) {
            $message .= ' Deadline: '.\Carbon\Carbon::parse($deadline)->toDayDateTimeString().'.';
        }
        $reviewer->notify(new TaskStatusNotification(
            $message,
            $task->id,
            'reviewer_added'
        ));

        return response()->json($task->fresh($this->eagerLoads()));
    }

    public function removeReviewer(Request $request, Task $task, User $reviewer): JsonResponse
    {
        $user = $request->user();
        if (! ($user->isLawyer() || $user->isAdmin())) {
            return response()->json(['message' => 'Only lawyers can remove reviewers.'], 403);
        }

        if (! $task->reviewers()->where('user_id', $reviewer->id)->exists()) {
            return response()->json(['message' => 'User is not a reviewer of this task.'], 404);
        }

        $task->reviewers()->detach($reviewer->id);

        TaskActivity::create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'action' => 'reviewer_removed',
            'meta' => ['reviewer_id' => $reviewer->id, 'reviewer_name' => $reviewer->name],
        ]);

        return response()->json($task->fresh($this->eagerLoads()));
    }

    public function uploadSigned(Request $request, Task $task): JsonResponse
    {
        $request->validate([
            'signature' => 'required|string',
        ]);

        $currentStep = $task->workflowRoute
            ? $task->workflowRoute->steps()->where('sort_order', $task->current_step)->first()
            : null;

        if ($currentStep) {
            if ($currentStep->action_type !== 'sign') {
                return response()->json(['message' => 'Signing is only allowed on sign steps.'], 422);
            }
            $engine = app(\App\Services\WorkflowEngine::class);
            if (! $engine->canActOnStep($currentStep, $request->user(), $task)) {
                return response()->json(['message' => 'You do not have permission to sign at this step.'], 403);
            }
        } else {
            if ($task->status !== TaskStatus::PendingInitiator) {
                return response()->json(['message' => 'Signing is only allowed during initiator negotiation phase.'], 422);
            }
            if ($task->initiator_id !== $request->user()->id) {
                return response()->json(['message' => 'Only initiator can sign the document.'], 403);
            }
        }

        $latestDoc = $task->documents()->orderByDesc('version')->first();
        if (! $latestDoc) {
            return response()->json(['message' => 'No document found to sign.'], 422);
        }

        $originalAbsPath = Storage::disk('local')->path($latestDoc->path);
        if (! file_exists($originalAbsPath)) {
            return response()->json(['message' => 'Document file not found.'], 404);
        }

        $lastVersion = $task->documents()->max('version') ?? 0;
        $newVersion = $lastVersion + 1;

        $signaturePath = $this->storeSignatureImage($task, $newVersion, $request->input('signature'));
        if (! $signaturePath) {
            return response()->json(['message' => 'Could not save signature.'], 422);
        }

        $stamper = app(\App\Services\SignatureStamper::class);
        $sigAbsPath = Storage::disk('local')->path($signaturePath);
        $srcExt = strtolower(pathinfo($latestDoc->path, PATHINFO_EXTENSION));

        $pdfAbsPath = $srcExt === 'pdf' ? $originalAbsPath : preg_replace('/\.(docx?)$/i', '.pdf', $originalAbsPath);
        if (! file_exists($pdfAbsPath) && $srcExt !== 'pdf') {
            $converter = app(\App\Services\DocToPdfConverter::class);
            $root = rtrim(str_replace('\\', '/', Storage::disk('local')->path('')), '/');
            $origNorm = str_replace('\\', '/', $originalAbsPath);
            $converter->convertIfNeeded(ltrim(str_replace($root, '', $origNorm), '/'));
        }

        $stampedAbsPath = $stamper->stampAtPlaceholderAndConvert($pdfAbsPath, $sigAbsPath, '{{COMPANY_SIGN}}');

        if (! $stampedAbsPath || ! file_exists($stampedAbsPath)) {
            return response()->json(['message' => 'Could not stamp signature on document.'], 500);
        }

        $normalize = fn (string $p) => str_replace('\\', '/', rtrim($p, '\\/'));
        $root = $normalize(Storage::disk('local')->path(''));
        $stamped = $normalize($stampedAbsPath);
        $finalRelPath = ltrim(str_replace($root, '', $stamped), '/');

        $doc = $task->documents()->create([
            'path' => $finalRelPath,
            'mime_type' => 'application/pdf',
            'version' => $newVersion,
            'is_signed' => true,
            'signed_by' => $request->user()->id,
            'signature_path' => $signaturePath,
        ]);

        app(TaskMainDocumentCommentMigrationService::class)->apply($task, $doc);

        TaskActivity::create([
            'task_id' => $task->id,
            'user_id' => $request->user()->id,
            'action' => 'signed_document_uploaded',
            'comment' => 'Document signed electronically',
            'meta' => ['document_id' => $doc->id, 'version' => $doc->version],
        ]);

        // Auto-advance the workflow after successful signing
        if ($currentStep && $task->workflow_route_id) {
            $engine = app(\App\Services\WorkflowEngine::class);
            $result = $engine->advance($task, $request->user(), 'approved');
            if (! $result['success']) {
                \Log::warning('Auto-advance after sign failed for task #'.$task->id.': '.($result['message'] ?? ''));
            }
            return response()->json($this->taskWithActions($task->fresh()), 201);
        }

        return response()->json($doc->fresh()->load('signer'), 201);
    }

    public function uploadEds(Request $request, Task $task): JsonResponse
    {
        $request->validate([
            'cms_signature' => 'required|string',
            'signed_data' => 'nullable|string',
        ]);

        $currentStep = $task->workflowRoute
            ? $task->workflowRoute->steps()->where('sort_order', $task->current_step)->first()
            : null;

        if ($currentStep) {
            if ($currentStep->action_type !== 'sign') {
                return response()->json(['message' => 'EDS signing is only allowed on sign steps.'], 422);
            }
            $engine = app(WorkflowEngine::class);
            if (! $engine->canActOnStep($currentStep, $request->user(), $task)) {
                return response()->json(['message' => 'You do not have permission to sign at this step.'], 403);
            }
        }

        $ncaService = app(\App\Services\NcaLayerService::class);
        $verification = $ncaService->verifyCmsSignature(
            $request->input('cms_signature'),
            $request->input('signed_data', '')
        );

        if (! $verification['valid']) {
            return response()->json(['message' => 'EDS signature verification failed: ' . ($verification['error'] ?? '')], 422);
        }

        $latestDoc = $task->documents()->orderByDesc('version')->first();
        if (! $latestDoc) {
            return response()->json(['message' => 'No document found to sign.'], 422);
        }

        $lastVersion = $task->documents()->max('version') ?? 0;
        $newVersion = $lastVersion + 1;

        $sigPath = $ncaService->storeSignature(
            $request->input('cms_signature'),
            'tasks/' . $task->id . '/eds-v' . $newVersion
        );

        $doc = $task->documents()->create([
            'path' => $latestDoc->path,
            'mime_type' => $latestDoc->mime_type,
            'version' => $newVersion,
            'is_signed' => true,
            'signed_by' => $request->user()->id,
            'signature_path' => $sigPath,
        ]);

        app(TaskMainDocumentCommentMigrationService::class)->apply($task, $doc);

        TaskActivity::create([
            'task_id' => $task->id,
            'user_id' => $request->user()->id,
            'action' => 'eds_signed',
            'comment' => 'Document signed with EDS (NCALayer)',
            'meta' => [
                'document_id' => $doc->id,
                'version' => $newVersion,
                'signer_info' => $verification['signer'] ?? [],
            ],
        ]);

        if ($currentStep && $task->workflow_route_id) {
            $engine = app(WorkflowEngine::class);
            $result = $engine->advance($task, $request->user(), 'approved');
            if (! $result['success']) {
                \Log::warning('Auto-advance after EDS sign failed for task #'.$task->id);
            }
            return response()->json($this->taskWithActions($task->fresh()), 201);
        }

        return response()->json($doc->fresh()->load('signer'), 201);
    }

    public function signature(Task $task, TaskDocument $document): mixed
    {
        if ($document->task_id !== $task->id || ! $document->signature_path) {
            abort(404);
        }

        return response()->file(Storage::disk('local')->path($document->signature_path), [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    private function storeSignatureImage(Task $task, $versionOrDoc, string $base64): ?string
    {
        if (! preg_match('/^data:image\/png;base64,(.+)$/', $base64, $matches)) {
            return null;
        }

        $imageData = base64_decode($matches[1], true);
        if ($imageData === false) {
            return null;
        }

        $version = is_object($versionOrDoc) ? $versionOrDoc->version : $versionOrDoc;
        $dir = "signatures/task-{$task->id}";
        $filename = "company-sig-v{$version}-".time().'.png';
        Storage::disk('local')->makeDirectory($dir);
        Storage::disk('local')->put("{$dir}/{$filename}", $imageData);

        return "{$dir}/{$filename}";
    }

    public function uploadFinalVersion(Request $request, Task $task): JsonResponse
    {
        $request->validate([
            'document' => 'required|file|mimes:doc,docx,pdf|max:20480',
        ]);

        $currentStep = $task->workflowRoute
            ? $task->workflowRoute->steps()->where('sort_order', $task->current_step)->first()
            : null;

        if (!$currentStep || $currentStep->action_type !== 'create_final') {
            return response()->json(['message' => 'Final version upload is only allowed on "create final" steps.'], 422);
        }

        $user = $request->user();
        $engine = app(WorkflowEngine::class);
        if (!$engine->canActOnStep($currentStep, $user, $task)) {
            return response()->json(['message' => 'You do not have permission to upload at this step.'], 403);
        }

        $lastVersion = $task->documents()->max('version') ?? 0;
        $doc = TaskDocument::storeUpload($task, $request->file('document'), $lastVersion + 1);

        app(TaskMainDocumentCommentMigrationService::class)->apply($task, $doc);

        TaskActivity::create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'action' => 'final_version_uploaded',
            'comment' => 'Final version uploaded after comment consolidation',
            'meta' => ['document_id' => $doc->id, 'version' => $doc->version],
        ]);

        $result = $engine->advance($task, $user, 'approved');
        if (!$result['success']) {
            \Log::warning('Auto-advance after final version upload failed for task #'.$task->id.': '.($result['message'] ?? ''));
        }

        return response()->json($this->taskWithActions($task->fresh()), 201);
    }

    public function uploadDocumentStep(Request $request, Task $task): JsonResponse
    {
        $request->validate([
            'document' => 'required|file|mimes:doc,docx,pdf|max:20480',
            'comment' => 'nullable|string|max:2000',
        ]);

        $currentStep = $task->workflowRoute
            ? $task->workflowRoute->steps()->where('sort_order', $task->current_step)->first()
            : null;

        if (! $currentStep || $currentStep->action_type !== 'upload_document') {
            return response()->json(['message' => 'Document upload is only allowed on upload-document steps.'], 422);
        }

        $user = $request->user();
        $engine = app(WorkflowEngine::class);
        if (! $engine->canActOnStep($currentStep, $user, $task)) {
            return response()->json(['message' => 'You do not have permission to upload at this step.'], 403);
        }

        $lastVersion = $task->documents()->max('version') ?? 0;
        $doc = TaskDocument::storeUpload($task, $request->file('document'), $lastVersion + 1);

        app(TaskMainDocumentCommentMigrationService::class)->apply($task, $doc);

        $comment = $request->input('comment', 'Document uploaded');

        TaskActivity::create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'action' => 'document_uploaded',
            'comment' => $comment,
            'meta' => ['document_id' => $doc->id, 'version' => $doc->version],
        ]);

        $result = $engine->advance($task, $user, 'approved', $comment);
        if (! $result['success']) {
            \Log::warning('Auto-advance after upload-document step failed for task #'.$task->id.': '.($result['message'] ?? ''));
        }

        return response()->json($this->taskWithActions($task->fresh()), 201);
    }

    public function getDocumentContent(Task $task): JsonResponse
    {
        $service = app(\App\Services\DocumentContentService::class);
        $html = $service->extractHtml($task);

        if ($html === null) {
            return response()->json(['message' => 'No editable document found for this task.'], 404);
        }

        return response()->json(['html' => $html]);
    }

    public function saveDocumentContent(Request $request, Task $task): JsonResponse
    {
        $request->validate([
            'html' => 'required|string',
        ]);

        $currentStep = $task->workflowRoute
            ? $task->workflowRoute->steps()->where('sort_order', $task->current_step)->first()
            : null;

        if (!$currentStep || $currentStep->action_type !== 'create_final') {
            return response()->json(['message' => 'Document editing is only allowed on "create final" steps.'], 422);
        }

        $user = $request->user();
        $engine = app(WorkflowEngine::class);
        if (!$engine->canActOnStep($currentStep, $user, $task)) {
            return response()->json(['message' => 'You do not have permission to edit at this step.'], 403);
        }

        try {
            $service = app(\App\Services\DocumentContentService::class);
            $service->saveHtml($task, $request->input('html'), $user);
        } catch (\Throwable $e) {
            \Log::error('Document content save failed for task #' . $task->id . ': ' . $e->getMessage());
            return response()->json(['message' => 'Failed to save document: ' . $e->getMessage()], 500);
        }

        return response()->json($this->taskWithActions($task->fresh()));
    }

    public function googleEdit(Request $request, Task $task): JsonResponse
    {
        $drive = app(\App\Services\GoogleDriveService::class);
        if (! $drive->isConfigured()) {
            return response()->json(['message' => 'Google Docs integration is not configured. Set it up in Admin Settings.'], 422);
        }

        $latestDoc = $task->documents()->orderByDesc('version')->first();
        if (! $latestDoc || ! $latestDoc->path) {
            return response()->json(['message' => 'No document found for this task.'], 404);
        }

        $absPath = \Illuminate\Support\Facades\Storage::disk('local')->path($latestDoc->path);
        if (! file_exists($absPath)) {
            return response()->json(['message' => 'Document file not found on disk.'], 404);
        }

        $ext = strtolower(pathinfo($absPath, PATHINFO_EXTENSION));
        if ($ext === 'pdf') {
            $docxOnDisk = preg_replace('/\.pdf$/i', '.docx', $absPath);
            if (file_exists($docxOnDisk)) {
                $absPath = $docxOnDisk;
            } else {
                $originalDocx = $task->documents()
                    ->where('mime_type', 'like', '%wordprocessingml%')
                    ->orderByDesc('version')
                    ->first();
                if ($originalDocx && $originalDocx->path) {
                    $absPath = \Illuminate\Support\Facades\Storage::disk('local')->path($originalDocx->path);
                } else {
                    return response()->json(['message' => 'No DOCX source found. Google Docs requires a Word document.'], 422);
                }
            }
        }

        try {
            $result = $drive->uploadDocx($absPath, 'Task #' . $task->id . ' - Edit');
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Upload to Google Drive failed: ' . $e->getMessage()], 500);
        }

        return response()->json($result);
    }

    public function googleSync(Request $request, Task $task): mixed
    {
        $request->validate([
            'file_id' => 'required|string',
            'delete_after' => 'nullable|boolean',
        ]);

        $drive = app(\App\Services\GoogleDriveService::class);
        if (! $drive->isConfigured()) {
            return response()->json(['message' => 'Google Docs integration is not configured.'], 422);
        }

        $fileId = $request->input('file_id');
        $tempDocx = tempnam(sys_get_temp_dir(), 'gsync_').'.docx';

        try {
            $drive->downloadDocx($fileId, $tempDocx);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Download from Google Drive failed: '.$e->getMessage()], 500);
        }

        $templateActivity = $task->activities()->where('action', 'document_generated')->first();
        $templateId = $templateActivity?->meta['template_id'] ?? null;
        $template = $templateId ? \App\Models\DocumentTemplate::find($templateId) : null;

        $generator = app(TemplateDocumentGenerator::class);
        $generator->replaceVariablesInDocxForTask(
            $tempDocx,
            $task,
            [],
            is_array($task->table_data) ? $task->table_data : [],
            $template,
        );

        $user = $request->user();
        $lastVersion = $task->documents()->max('version') ?? 0;

        $storagePath = 'tasks/'.$task->id.'/google-sync-v'.($lastVersion + 1).'.docx';
        \Illuminate\Support\Facades\Storage::disk('local')->put($storagePath, file_get_contents($tempDocx));

        $pdfPath = null;
        try {
            $converter = app(\App\Services\DocToPdfConverter::class);
            $absDocx = \Illuminate\Support\Facades\Storage::disk('local')->path($storagePath);
            $pdfAbsPath = preg_replace('/\.docx$/i', '.pdf', $absDocx);
            $pdfPath = $converter->convertFromAbsPath($absDocx, $pdfAbsPath);
        } catch (\Throwable) {
            // Keep DOCX if PDF conversion fails
        }

        $docPath = $storagePath;
        $docMime = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
        if ($pdfPath && file_exists($pdfPath)) {
            $docPath = preg_replace('/\.docx$/i', '.pdf', $storagePath);
            $docMime = 'application/pdf';
        }

        $doc = $task->documents()->create([
            'path' => $docPath,
            'mime_type' => $docMime,
            'version' => $lastVersion + 1,
        ]);

        app(TaskMainDocumentCommentMigrationService::class)->apply($task, $doc);

        \App\Models\TaskActivity::create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'action' => 'document_edited_google',
            'comment' => 'Document synced from Google Docs',
            'meta' => ['document_id' => $doc->id, 'version' => $doc->version, 'google_file_id' => $fileId],
        ]);

        if ($request->boolean('delete_after')) {
            $drive->deleteFile($fileId);
        }

        @unlink($tempDocx);

        return response()->json($this->taskWithActions($task->fresh()));
    }

    public function summaryReport(Task $task): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $task->load(['category', 'partner', 'initiator', 'assignedLawyer', 'documents', 'activities.user', 'reviewers', 'stepCompletions.step']);

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\TaskSummaryExport($task),
            'task-'.$task->id.'-summary.xlsx'
        );
    }

    // --- Private helpers ---

    private function approveByManager(Task $task, User $user): array
    {
        if ($task->isStandardRoute()) {
            $task->update(['status' => TaskStatus::PendingLawyer, 'current_step' => 2]);
            $this->notifyLawyer($task, 'Task #'.$task->id.' requires your legal review.');
        } else {
            $task->update(['status' => TaskStatus::Approved, 'current_step' => 2]);
            GenerateTaskPdfJob::dispatch($task);
            $task->initiator->notify(new TaskStatusNotification(
                'Task #'.$task->id.' has been approved.',
                $task->id,
                'approved'
            ));
        }
        return ['action' => 'approved'];
    }

    private function approveByLawyer(Task $task, User $user): array
    {
        $task->update(['status' => TaskStatus::PendingInitiator, 'current_step' => 3]);
        $task->initiator->notify(new TaskStatusNotification(
            'Task #'.$task->id.' has been reviewed. Please negotiate with the counterparty and re-upload the signed version.',
            $task->id,
            'pending_initiator'
        ));
        return ['action' => 'approved'];
    }

    private function resubmitByInitiator(Task $task, User $user, Request $request): array
    {
        $task->update(['status' => TaskStatus::PendingFinalLawyer, 'current_step' => 4]);
        $this->notifyLawyer($task, 'Task #'.$task->id.' signed document requires your final review.');
        return ['action' => 'resubmitted'];
    }

    private function approveByFinalLawyer(Task $task, User $user): array
    {
        $task->update(['status' => TaskStatus::PendingFinalManager, 'current_step' => 5]);
        $this->notifyManagers($task, 'Task #'.$task->id.' requires your final approval.');
        return ['action' => 'approved'];
    }

    private function approveByFinalManager(Task $task, User $user): array
    {
        $task->update(['status' => TaskStatus::Approved, 'current_step' => 6]);
        GenerateTaskPdfJob::dispatch($task);
        $task->initiator->notify(new TaskStatusNotification(
            'Task #'.$task->id.' has been fully approved and archived.',
            $task->id,
            'approved'
        ));
        return ['action' => 'approved'];
    }

    private function notifyManagers(Task $task, string $message): void
    {
        User::where('role', 'manager')->each(function (User $manager) use ($task, $message) {
            $manager->notify(new TaskStatusNotification($message, $task->id, 'pending'));
        });
    }

    private function notifyLawyer(Task $task, string $message): void
    {
        if ($task->assigned_lawyer_id) {
            $task->assignedLawyer->notify(new TaskStatusNotification($message, $task->id, 'pending'));
        } else {
            User::where('role', 'lawyer')->each(function (User $lawyer) use ($task, $message) {
                $lawyer->notify(new TaskStatusNotification($message, $task->id, 'pending'));
            });
        }
    }

    private function eagerLoads(): array
    {
        return ['category', 'partner', 'initiator', 'assignedLawyer', 'documents', 'activities.user', 'reviewers', 'workflowRoute.steps'];
    }

    private function taskWithActions(Task $task, ?User $actor = null): array
    {
        $actor = $actor ?? request()->user();
        $task = $task->fresh($this->eagerLoads());
        $data = $task->toArray();
        $engine = app(WorkflowEngine::class);
        $data['available_actions'] = $engine->getAvailableOutcomes($task, $actor);
        $data['active_steps'] = $engine->getActiveSteps($task)->map(fn ($c) => [
            'step_id' => $c->workflow_step_id,
            'step_name' => $c->step?->name,
            'role' => $c->step?->role,
            'action_type' => $c->step?->action_type,
            'status' => $c->status,
            'started_at' => optional($c->created_at)?->toIso8601String(),
        ])->values()->toArray();

        $currentStep = $task->workflowRoute
            ? $task->workflowRoute->steps()->where('sort_order', $task->current_step)->first()
            : null;
        $data['current_step_action_type'] = $currentStep?->action_type ?? 'review';
        $data['current_step_name'] = $currentStep?->name;
        $data['current_step_role'] = $currentStep?->role;
        $data['can_edit_attachments'] = (bool) ($currentStep?->config['can_edit_attachments'] ?? false);

        return $data;
    }
}
