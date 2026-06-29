<?php

namespace App\Filament\Pages;

use App\Models\Task;
use App\Models\TaskStepCompletion;
use App\Models\WorkflowRoute;
use App\Models\WorkflowStep;
use App\Models\WorkflowTransition;
use App\Services\AiWorkflowService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;

class WorkflowBuilder extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-squares-plus';

    protected static string|\UnitEnum|null $navigationGroup = 'Workflow';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Flow Builder';

    protected static ?string $navigationLabel = 'Flow Builder';

    protected string $view = 'filament.pages.workflow-builder';

    #[Url]
    public ?int $record = null;

    public array $routes = [];
    public ?array $canvasData = null;
    public ?string $routeName = null;
    public ?string $routeSlug = null;
    public ?string $routeDescription = null;
    public bool $routeIsActive = false;
    public bool $aiAvailable = false;

    public function mount(): void
    {
        $this->routes = WorkflowRoute::orderBy('name')->get()->map(fn ($r) => [
            'id' => $r->id,
            'name' => $r->name,
            'slug' => $r->slug,
        ])->toArray();

        $this->aiAvailable = app(AiWorkflowService::class)->isAvailable();

        if ($this->record) {
            $this->loadRoute($this->record);
        }
    }

    public function loadRoute(int $routeId): void
    {
        $route = WorkflowRoute::with(['steps', 'transitions'])->find($routeId);
        if (! $route) {
            return;
        }

        $this->record = $routeId;
        $this->routeName = $route->name;
        $this->routeSlug = $route->slug;
        $this->routeDescription = $route->description;
        $this->routeIsActive = $route->is_active;

        if ($route->canvas_data) {
            $this->canvasData = $route->canvas_data;
        } else {
            $this->canvasData = $this->buildCanvasFromSteps($route);
        }
    }

    public function createNewRoute(): void
    {
        $route = WorkflowRoute::create([
            'name' => 'New Route',
            'slug' => 'new-route-' . Str::random(6),
            'description' => '',
            'is_active' => true,
            'is_default' => false,
            'canvas_data' => [
                'nodes' => [],
                'edges' => [],
            ],
        ]);

        $this->routes = WorkflowRoute::orderBy('name')->get()->map(fn ($r) => [
            'id' => $r->id,
            'name' => $r->name,
            'slug' => $r->slug,
        ])->toArray();

        $this->loadRoute($route->id);

        Notification::make()->title('New route created')->success()->send();
    }

    public function saveCanvas(array $data): void
    {
        if (! $this->record) {
            Notification::make()->title('No route selected')->danger()->send();
            return;
        }

        $route = WorkflowRoute::find($this->record);
        if (! $route) {
            return;
        }

        $nodes = $data['nodes'] ?? [];
        $edges = $data['edges'] ?? [];

        $stepNodes = array_filter($nodes, fn ($n) => $n['type'] === 'step');

        if (empty($stepNodes)) {
            Notification::make()
                ->title('Validation error')
                ->body('Flow must have at least one step node.')
                ->danger()
                ->send();
            return;
        }

        $route->update([
            'name' => $this->routeName ?? $route->name,
            'slug' => $this->routeSlug ?? $route->slug,
            'description' => $this->routeDescription ?? $route->description,
            'is_active' => $this->routeIsActive,
            'canvas_data' => $data,
        ]);

        $oldStepsByOrder = $route->steps()->orderBy('sort_order')->get()
            ->keyBy('sort_order');

        $route->transitions()->delete();
        $route->steps()->delete();

        usort($stepNodes, fn ($a, $b) => ($a['x'] ?? 0) <=> ($b['x'] ?? 0));

        $nodeToStepId = [];
        $oldToNewStepMap = [];
        $sortOrder = 1;

        foreach ($stepNodes as $node) {
            $step = WorkflowStep::create([
                'workflow_route_id' => $route->id,
                'name' => $node['label'] ?? 'Step '.$sortOrder,
                'role' => $node['role'] ?? 'manager',
                'action_type' => $node['actionType'] ?? 'review',
                'sort_order' => $sortOrder,
                'duration_days' => max(0, (int) ($node['durationDays'] ?? 1)),
                'config' => $node['config'] ?? null,
            ]);
            $nodeToStepId[$node['id']] = $step->id;

            if ($oldStep = $oldStepsByOrder->get($sortOrder)) {
                $oldToNewStepMap[$oldStep->id] = $step->id;
            }
            $sortOrder++;
        }

        $this->migrateExistingTasks($route, $oldToNewStepMap);

        $edgePriority = 0;
        foreach ($edges as $edge) {
            $fromId = $nodeToStepId[$edge['from']] ?? null;
            $toId = $nodeToStepId[$edge['to']] ?? null;

            if ($fromId && $toId) {
                WorkflowTransition::create([
                    'workflow_route_id' => $route->id,
                    'from_step_id' => $fromId,
                    'to_step_id' => $toId,
                    'condition' => $edge['condition'] ?? null,
                    'priority' => $edge['priority'] ?? $edgePriority,
                ]);
            }
            $edgePriority++;
        }

        $this->routes = WorkflowRoute::orderBy('name')->get()->map(fn ($r) => [
            'id' => $r->id,
            'name' => $r->name,
            'slug' => $r->slug,
        ])->toArray();

        Notification::make()
            ->title('Flow saved')
            ->body("Saved {$route->name} with ".count($stepNodes)." steps.")
            ->success()
            ->send();
    }

    private function migrateExistingTasks(WorkflowRoute $route, array $oldToNewStepMap): void
    {
        if (empty($oldToNewStepMap)) {
            return;
        }

        $tasks = Task::where('workflow_route_id', $route->id)
            ->whereNotNull('current_workflow_step_id')
            ->get();

        foreach ($tasks as $task) {
            $newStepId = $oldToNewStepMap[$task->current_workflow_step_id] ?? null;
            if (! $newStepId) {
                $newStep = WorkflowStep::where('workflow_route_id', $route->id)
                    ->where('sort_order', $task->current_step)
                    ->first();
                $newStepId = $newStep?->id;
            }
            if ($newStepId && $newStepId !== $task->current_workflow_step_id) {
                $task->update(['current_workflow_step_id' => $newStepId]);

                TaskStepCompletion::where('task_id', $task->id)
                    ->where('status', 'active')
                    ->get()
                    ->each(function ($c) use ($oldToNewStepMap) {
                        $mapped = $oldToNewStepMap[$c->workflow_step_id] ?? null;
                        if ($mapped) {
                            $c->update(['workflow_step_id' => $mapped]);
                        }
                    });

                $hasActive = TaskStepCompletion::where('task_id', $task->id)
                    ->where('workflow_step_id', $newStepId)
                    ->where('status', 'active')
                    ->exists();
                if (! $hasActive) {
                    TaskStepCompletion::create([
                        'task_id' => $task->id,
                        'workflow_step_id' => $newStepId,
                        'status' => 'active',
                    ]);
                }
            }
        }
    }

    public function generateWithAi(string $description): void
    {
        if (! $description || strlen(trim($description)) < 10) {
            Notification::make()
                ->title('Description too short')
                ->body('Please provide a more detailed description of the workflow you want.')
                ->danger()
                ->send();

            return;
        }

        $service = app(AiWorkflowService::class);
        $result = $service->generateWorkflow($description);

        if ($result['status'] !== 'success') {
            Notification::make()
                ->title('AI generation failed')
                ->body($result['message'] ?? 'Unknown error')
                ->danger()
                ->send();

            return;
        }

        $workflow = $result['workflow'];

        $existingSlug = WorkflowRoute::where('slug', $workflow['slug'])->exists();
        if ($existingSlug) {
            $workflow['slug'] .= '-'.Str::random(4);
        }

        $route = WorkflowRoute::create([
            'name' => $workflow['name'],
            'slug' => $workflow['slug'],
            'description' => $workflow['description'],
            'is_active' => true,
            'is_default' => false,
        ]);

        $stepIndexToId = [];
        $sortOrder = 1;

        foreach ($workflow['steps'] as $i => $step) {
            $created = WorkflowStep::create([
                'workflow_route_id' => $route->id,
                'name' => $step['name'],
                'role' => $step['role'],
                'action_type' => $step['action_type'],
                'sort_order' => $sortOrder,
                'duration_days' => (int) ($step['duration_days'] ?? 1),
            ]);
            $stepIndexToId[$i] = $created->id;
            $sortOrder++;
        }

        foreach ($workflow['transitions'] as $t) {
            $fromId = $stepIndexToId[$t['from_step']] ?? null;
            $toId = $stepIndexToId[$t['to_step']] ?? null;

            if ($fromId && $toId) {
                WorkflowTransition::create([
                    'workflow_route_id' => $route->id,
                    'from_step_id' => $fromId,
                    'to_step_id' => $toId,
                    'condition' => $t['condition'],
                    'priority' => $t['priority'] ?? 0,
                ]);
            }
        }

        $route->load(['steps', 'transitions']);
        $canvasData = $this->buildCanvasFromSteps($route);
        $route->update(['canvas_data' => $canvasData]);

        $this->routes = WorkflowRoute::orderBy('name')->get()->map(fn ($r) => [
            'id' => $r->id,
            'name' => $r->name,
            'slug' => $r->slug,
        ])->toArray();

        $this->loadRoute($route->id);

        $stepCount = count($workflow['steps']);
        Notification::make()
            ->title('AI workflow generated')
            ->body("Created \"{$workflow['name']}\" with {$stepCount} steps. Review and click Save Flow when ready.")
            ->success()
            ->send();
    }

    private function buildCanvasFromSteps(WorkflowRoute $route): array
    {
        $steps = $route->steps()->orderBy('sort_order')->get();

        $stepIdToNodeId = [];
        $nodes = [];
        $edges = [];

        $spacing = 200;
        $x = 2000 - (int) (($steps->count() * $spacing) / 2);
        $y = 2000;

        foreach ($steps as $step) {
            $nodeId = 'step-'.$step->id;
            $stepIdToNodeId[$step->id] = $nodeId;
            $nodeData = [
                'id' => $nodeId,
                'type' => 'step',
                'x' => $x,
                'y' => $y,
                'label' => $step->name,
                'role' => $step->role,
                'actionType' => $step->action_type,
                'durationDays' => (int) ($step->duration_days ?? 1),
            ];
            if ($step->config) {
                $nodeData['config'] = $step->config;
            }
            $nodes[] = $nodeData;
            $x += $spacing;
        }

        if ($route->transitions->isNotEmpty()) {
            foreach ($route->transitions as $t) {
                $from = $stepIdToNodeId[$t->from_step_id] ?? null;
                $to = $stepIdToNodeId[$t->to_step_id] ?? null;
                if ($from && $to) {
                    $edge = ['from' => $from, 'to' => $to];
                    if ($t->condition) {
                        $edge['condition'] = $t->condition;
                    }
                    $edges[] = $edge;
                }
            }
        } else {
            $stepNodeIds = array_column($nodes, 'id');
            for ($i = 0; $i < count($stepNodeIds) - 1; $i++) {
                $edges[] = ['from' => $stepNodeIds[$i], 'to' => $stepNodeIds[$i + 1]];
            }
        }

        return ['nodes' => $nodes, 'edges' => $edges];
    }

    public static function getRouteName(?\Filament\Panel $panel = null): string
    {
        return 'filament.admin.pages.workflow-builder';
    }
}
