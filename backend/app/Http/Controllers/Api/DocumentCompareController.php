<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TaskDocument;
use App\Services\AiDocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentCompareController extends Controller
{
    public function compare(Request $request): JsonResponse
    {
        $request->validate([
            'task_id' => 'required|exists:tasks,id',
            'signed_document_id' => 'required|exists:task_documents,id',
            'original_document_id' => 'nullable|exists:task_documents,id',
        ]);

        $signed = TaskDocument::findOrFail($request->input('signed_document_id'));

        $original = $request->filled('original_document_id')
            ? TaskDocument::findOrFail($request->input('original_document_id'))
            : TaskDocument::where('task_id', $request->input('task_id'))
                ->where('id', '!=', $signed->id)
                ->where('is_signed', false)
                ->orderByDesc('version')
                ->first();

        if (! $original) {
            return response()->json([
                'status' => 'error',
                'message' => 'No original document found for comparison.',
            ], 422);
        }

        $service = app(AiDocumentService::class);
        $result = $service->compareDocuments($original, $signed);

        return response()->json($result);
    }

    public function analyze(Request $request): JsonResponse
    {
        $request->validate([
            'document_id' => 'required|exists:task_documents,id',
        ]);

        $document = TaskDocument::findOrFail($request->input('document_id'));
        $service = app(AiDocumentService::class);
        $result = $service->analyzeDocument($document);

        return response()->json($result);
    }

    public function validate(Request $request): JsonResponse
    {
        $request->validate([
            'document_id' => 'required|exists:task_documents,id',
            'template_name' => 'nullable|string',
        ]);

        $document = TaskDocument::findOrFail($request->input('document_id'));
        $service = app(AiDocumentService::class);
        $result = $service->validateDocument($document, $request->input('template_name', ''));

        return response()->json($result);
    }
}
