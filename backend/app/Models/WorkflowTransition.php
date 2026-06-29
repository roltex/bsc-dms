<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowTransition extends Model
{
    protected $fillable = [
        'workflow_route_id', 'from_step_id', 'to_step_id', 'condition', 'priority',
    ];

    protected function casts(): array
    {
        return [
            'condition' => 'array',
            'priority' => 'integer',
        ];
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(WorkflowRoute::class, 'workflow_route_id');
    }

    public function fromStep(): BelongsTo
    {
        return $this->belongsTo(WorkflowStep::class, 'from_step_id');
    }

    public function toStep(): BelongsTo
    {
        return $this->belongsTo(WorkflowStep::class, 'to_step_id');
    }

    public function isConditional(): bool
    {
        $type = $this->condition['type'] ?? 'always';

        return $type !== 'always';
    }

    public function evaluateFor(Task $task, string $outcome): bool
    {
        $cond = $this->condition;
        if (! $cond || empty($cond['type'])) {
            return true; // no condition = always
        }

        return match ($cond['type']) {
            'always' => true,
            'approved' => $outcome === 'approved',
            'rejected' => $outcome === 'rejected',
            'needs_revision' => $outcome === 'needs_revision',
            'amount_gte' => $outcome === 'approved' && ($task->amount ?? 0) >= ($cond['value'] ?? 0),
            'amount_lt' => $outcome === 'approved' && ($task->amount ?? 0) < ($cond['value'] ?? 0),
            'has_document' => $outcome === 'approved' && $task->documents()->exists(),
            'is_signed' => $outcome === 'approved' && $task->documents()->where('is_signed', true)->exists(),
            'requires_gm' => $outcome === 'approved' && ($task->amount ?? 0) >= (float) \App\Models\Setting::get('gm_approval_threshold', 0),
            'department' => $outcome === 'approved' && strtolower($task->category?->name ?? '') === strtolower($cond['value'] ?? ''),
            default => true,
        };
    }

    public function getConditionLabel(): string
    {
        $cond = $this->condition;
        if (! $cond || empty($cond['type'])) {
            return '';
        }

        return match ($cond['type']) {
            'always' => '',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'needs_revision' => 'Needs Revision',
            'amount_gte' => 'Amount >= '.number_format($cond['value'] ?? 0),
            'amount_lt' => 'Amount < '.number_format($cond['value'] ?? 0),
            'has_document' => 'Has Document',
            'is_signed' => 'Is Signed',
            'requires_gm' => 'Requires GM (Amount >= '.number_format((float) \App\Models\Setting::get('gm_approval_threshold', 0)).')',
            'department' => 'Department: '.($cond['value'] ?? ''),
            default => ucfirst(str_replace('_', ' ', $cond['type'])),
        };
    }
}
