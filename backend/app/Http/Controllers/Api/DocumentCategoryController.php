<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DocumentCategory;
use Illuminate\Http\JsonResponse;

class DocumentCategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $categories = DocumentCategory::query()
            ->with('defaultLawyer:id,name,email')
            ->orderBy('name')
            ->get();

        return response()->json($categories);
    }
}
