<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PartnerAccessToken extends Model
{
    protected $fillable = [
        'task_id', 'partner_id', 'workflow_step_id', 'token',
        'partner_email', 'expires_at', 'used_at', 'action_taken', 'comment',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function workflowStep(): BelongsTo
    {
        return $this->belongsTo(WorkflowStep::class);
    }

    public function isValid(): bool
    {
        return ! $this->used_at && $this->expires_at->isFuture();
    }

    public static function generateForTask(Task $task, WorkflowStep $step): ?self
    {
        $partner = $task->partner;
        if (! $partner || ! $partner->email) {
            return null;
        }

        $existing = self::where('task_id', $task->id)
            ->where('workflow_step_id', $step->id)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();

        if ($existing) {
            return $existing;
        }

        return self::create([
            'task_id' => $task->id,
            'partner_id' => $partner->id,
            'workflow_step_id' => $step->id,
            'token' => Str::random(64),
            'partner_email' => $partner->email,
            'expires_at' => now()->addDays(7),
        ]);
    }
}
