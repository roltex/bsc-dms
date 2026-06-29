<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowStep extends Model
{
    protected $fillable = [
        'workflow_route_id', 'name', 'role', 'action_type', 'sort_order', 'duration_days', 'config',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'sort_order' => 'integer',
            'duration_days' => 'integer',
        ];
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(WorkflowRoute::class, 'workflow_route_id');
    }

    public function outgoingTransitions(): HasMany
    {
        return $this->hasMany(WorkflowTransition::class, 'from_step_id');
    }

    public function incomingTransitions(): HasMany
    {
        return $this->hasMany(WorkflowTransition::class, 'to_step_id');
    }

    public function isTerminal(): bool
    {
        return $this->outgoingTransitions()->count() === 0;
    }
}
