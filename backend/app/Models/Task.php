<?php

namespace App\Models;

use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    protected $fillable = [
        'document_category_id',
        'partner_id',
        'initiator_id',
        'assigned_lawyer_id',
        'route_type',
        'workflow_route_id',
        'current_workflow_step_id',
        'status',
        'current_step',
        'deadline',
        'commercial_terms',
        'table_data',
        'step_durations',
        'amount',
        'validity_from',
        'validity_to',
        'registration_number',
        'fast_tracked',
    ];

    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
            'deadline' => 'datetime',
            'validity_from' => 'date',
            'validity_to' => 'date',
            'fast_tracked' => 'boolean',
            'amount' => 'decimal:2',
            'table_data' => 'array',
            'step_durations' => 'array',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(DocumentCategory::class, 'document_category_id');
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiator_id');
    }

    public function assignedLawyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_lawyer_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(TaskDocument::class)->orderByDesc('version');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(TaskActivity::class)->orderByDesc('created_at');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class);
    }

    public function reviewers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_reviewers')
            ->withPivot('deadline')
            ->withTimestamps();
    }

    public function workflowRoute(): BelongsTo
    {
        return $this->belongsTo(WorkflowRoute::class);
    }

    public function currentWorkflowStep(): BelongsTo
    {
        return $this->belongsTo(WorkflowStep::class, 'current_workflow_step_id');
    }

    public function stepCompletions(): HasMany
    {
        return $this->hasMany(TaskStepCompletion::class);
    }

    public function activeStepCompletions(): HasMany
    {
        return $this->hasMany(TaskStepCompletion::class)->where('status', 'active');
    }

    public function isStandardRoute(): bool
    {
        return $this->route_type === 'standard';
    }

    public function isOverdue(): bool
    {
        return $this->deadline && $this->deadline->isPast() && $this->status->isPending();
    }

    public function totalSteps(): int
    {
        if ($this->workflowRoute) {
            return $this->workflowRoute->steps()->count();
        }

        return $this->isStandardRoute() ? 6 : 2;
    }
}
