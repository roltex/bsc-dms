<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlaceholderVariable extends Model
{
    protected $fillable = [
        'key', 'label', 'description', 'source', 'default_value',
        'is_system', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public const SOURCES = [
        'partner' => 'Partner Data',
        'task' => 'Task / Form Field',
        'settings' => 'System Settings',
        'user' => 'Current User',
        'date' => 'Date / Time',
        'signature' => 'Signature',
        'auto' => 'Auto-generated',
        'manual' => 'Manual Input',
        'company' => 'Company Info',
        'workflow' => 'Workflow / Process',
        'category' => 'Document Category',
        'template' => 'Document Template',
        'financial' => 'Financial / Amount',
        'legal' => 'Legal / Compliance',
        'address' => 'Address / Location',
        'contact' => 'Contact Info',
        'bank' => 'Banking Details',
        'approval' => 'Approval / Decision',
        'numbering' => 'Numbering / Sequence',
        'custom' => 'Custom / Other',
    ];
}
