<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TemplateTable extends Model
{
    protected $fillable = [
        'document_template_id',
        'name',
        'shortcode',
        'columns',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'columns' => 'array',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class, 'document_template_id');
    }

    public const INVENTORY_FIELDS = [
        'title' => 'Title',
        'description' => 'Description',
        'category' => 'Category',
        'price' => 'Price',
        'currency' => 'Currency',
        'serial_number' => 'Serial Number',
        'model_number' => 'Model Number',
        'status' => 'Status',
    ];
}
