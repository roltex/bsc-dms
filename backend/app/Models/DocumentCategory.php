<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentCategory extends Model
{
    protected $fillable = [
        'name',
        'code',
        'default_lawyer_id',
    ];

    public function defaultLawyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'default_lawyer_id');
    }

    public function templates(): HasMany
    {
        return $this->hasMany(DocumentTemplate::class, 'document_category_id');
    }

    public function workflowRoutes(): BelongsToMany
    {
        return $this->belongsToMany(WorkflowRoute::class, 'category_workflow_route');
    }
}
