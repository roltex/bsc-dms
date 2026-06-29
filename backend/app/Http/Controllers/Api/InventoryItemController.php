<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInventoryItemRequest;
use App\Http\Requests\UpdateInventoryItemRequest;
use App\Models\InventoryItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class InventoryItemController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = InventoryItem::query();

        if ($request->filled('search')) {
            $s = '%'.$request->input('search').'%';
            $query->where(function ($q) use ($s) {
                $q->where('title', 'like', $s)
                    ->orWhere('serial_number', 'like', $s)
                    ->orWhere('model_number', 'like', $s)
                    ->orWhere('description', 'like', $s);
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        $items = $query->orderBy('created_at', 'desc')->paginate(
            $request->integer('per_page', 15)
        );

        return response()->json($items);
    }

    public function store(StoreInventoryItemRequest $request): JsonResponse
    {
        $data = $request->validated();

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('inventory-items', 'public');
            unset($data['image']);
        }

        unset($data['image']);

        $item = InventoryItem::create($data);

        return response()->json($item, 201);
    }

    public function show(InventoryItem $inventoryItem): JsonResponse
    {
        return response()->json($inventoryItem);
    }

    public function update(UpdateInventoryItemRequest $request, InventoryItem $inventoryItem): JsonResponse
    {
        $data = $request->validated();

        if ($request->hasFile('image')) {
            if ($inventoryItem->image_path) {
                Storage::disk('public')->delete($inventoryItem->image_path);
            }
            $data['image_path'] = $request->file('image')->store('inventory-items', 'public');
        }

        unset($data['image']);

        $inventoryItem->update($data);

        return response()->json($inventoryItem->fresh());
    }

    public function destroy(InventoryItem $inventoryItem): JsonResponse
    {
        if ($inventoryItem->image_path) {
            Storage::disk('public')->delete($inventoryItem->image_path);
        }

        $inventoryItem->delete();

        return response()->json(null, 204);
    }
}
