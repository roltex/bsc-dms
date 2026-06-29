<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowRoute extends Model
{
    protected $fillable = [
        'name', 'slug', 'description', 'is_active', 'is_default', 'canvas_data',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'canvas_data' => 'array',
        ];
    }

    public function steps(): HasMany
    {
        return $this->hasMany(WorkflowStep::class)->orderBy('sort_order');
    }

    public function transitions(): HasMany
    {
        return $this->hasMany(WorkflowTransition::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function documentCategories(): BelongsToMany
    {
        return $this->belongsToMany(DocumentCategory::class, 'category_workflow_route');
    }

    public function firstStep(): ?WorkflowStep
    {
        return $this->steps()->orderBy('sort_order')->first();
    }

    public function lastStep(): ?WorkflowStep
    {
        return $this->hasMany(WorkflowStep::class)->orderByDesc('sort_order')->first();
    }
}
