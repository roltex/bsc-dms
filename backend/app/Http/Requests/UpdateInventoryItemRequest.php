<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInventoryItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $itemId = $this->route('inventory_item')?->id ?? $this->route('inventory_item');

        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category' => ['sometimes', 'required', 'string', 'max:100'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'currency' => ['nullable', 'string', 'max:10'],
            'serial_number' => ['nullable', 'string', 'max:255', Rule::unique('inventory_items', 'serial_number')->ignore($itemId)],
            'model_number' => ['nullable', 'string', 'max:255'],
            'image' => ['nullable', 'file', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
            'status' => ['nullable', 'string', Rule::in(\App\Models\InventoryItem::STATUSES)],
        ];
    }
}
