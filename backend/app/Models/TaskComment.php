<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaskComment extends Model
{
    protected $fillable = [
        'task_id',
        'document_id',
        'user_id',
        'page',
        'x_percent',
        'y_percent',
        'body',
        'resolved',
        'parent_id',
    ];

    protected function casts(): array
    {
        return [
            'page' => 'integer',
            'x_percent' => 'decimal:2',
            'y_percent' => 'decimal:2',
            'resolved' => 'boolean',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(TaskDocument::class, 'document_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('created_at');
    }
}
