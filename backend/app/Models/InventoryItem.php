<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryItem extends Model
{
    protected $fillable = [
        'title',
        'description',
        'category',
        'price',
        'currency',
        'serial_number',
        'model_number',
        'image_path',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
        ];
    }

    public const CATEGORIES = [
        'Laptop',
        'Desktop',
        'Monitor',
        'Keyboard',
        'Mouse',
        'Headset',
        'Phone',
        'Tablet',
        'Printer',
        'Scanner',
        'Server',
        'Network Equipment',
        'Furniture',
        'Office Supplies',
        'Other',
    ];

    public const STATUSES = [
        'available',
        'in_use',
        'damaged',
        'retired',
    ];
}
