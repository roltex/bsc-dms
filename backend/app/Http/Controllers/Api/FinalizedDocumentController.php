<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FinalizedDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FinalizedDocumentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = FinalizedDocument::query()->with('user:id,name');

        if ($request->filled('search')) {
            $s = $request->input('search');
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('notes', 'like', "%{$s}%")
                  ->orWhere('category', 'like', "%{$s}%")
                  ->orWhereHas('user', fn ($uq) => $uq->where('name', 'like', "%{$s}%"));
            });
        }

        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        if ($request->filled('file_type')) {
            $type = strtolower($request->input('file_type'));
            $query->where(function ($q) use ($type) {
                switch ($type) {
                    case 'pdf':
                        $q->where('name', 'like', '%.pdf')
                          ->orWhere('mime_type', 'like', '%pdf%');
                        break;
                    case 'docx':
                    case 'doc':
                        $q->where('name', 'like', '%.doc')
                          ->orWhere('name', 'like', '%.docx')
                          ->orWhere('mime_type', 'like', '%word%')
                          ->orWhere('mime_type', 'like', '%document%');
                        break;
                    case 'xlsx':
                    case 'xls':
                        $q->where('name', 'like', '%.xls')
                          ->orWhere('name', 'like', '%.xlsx')
                          ->orWhere('mime_type', 'like', '%sheet%')
                          ->orWhere('mime_type', 'like', '%excel%');
                        break;
                    case 'image':
                        $q->where('mime_type', 'like', 'image/%');
                        break;
                    case 'other':
                        $q->where('mime_type', 'not like', '%pdf%')
                          ->where('mime_type', 'not like', '%word%')
                          ->where('mime_type', 'not like', '%document%')
                          ->where('mime_type', 'not like', '%sheet%')
                          ->where('mime_type', 'not like', '%excel%')
                          ->where('mime_type', 'not like', 'image/%');
                        break;
                }
            });
        }

        if ($request->filled('date_from')) {
            try {
                $query->whereDate('created_at', '>=', \Carbon\Carbon::parse($request->input('date_from'))->toDateString());
            } catch (\Throwable) {
                // ignore invalid date
            }
        }

        if ($request->filled('date_to')) {
            try {
                $query->whereDate('created_at', '<=', \Carbon\Carbon::parse($request->input('date_to'))->toDateString());
            } catch (\Throwable) {
                // ignore invalid date
            }
        }

        if ($request->filled('min_size')) {
            $query->where('size', '>=', $request->integer('min_size'));
        }

        if ($request->filled('max_size')) {
            $query->where('size', '<=', $request->integer('max_size'));
        }

        $sortField = $request->input('sort', 'created_at');
        $sortDir = $request->input('dir', 'desc');
        $allowed = ['created_at', 'name', 'size', 'category'];
        if (in_array($sortField, $allowed)) {
            $query->orderBy($sortField, $sortDir === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderByDesc('created_at');
        }

        $docs = $query->paginate($request->integer('per_page', 20));

        return response()->json($docs);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'document' => 'required|file|max:20480',
            'category' => 'required|string|in:' . implode(',', array_keys(FinalizedDocument::CATEGORIES)),
            'notes' => 'nullable|string|max:1000',
        ]);

        $doc = FinalizedDocument::storeUpload(
            $request->user()->id,
            $request->file('document'),
            $request->input('category'),
            $request->input('notes')
        );

        return response()->json($doc->load('user:id,name'), 201);
    }

    public function show(FinalizedDocument $finalizedDocument): JsonResponse
    {
        return response()->json($finalizedDocument->load('user:id,name'));
    }

    public function destroy(FinalizedDocument $finalizedDocument): JsonResponse
    {
        Storage::disk('local')->delete($finalizedDocument->path);
        $finalizedDocument->delete();

        return response()->json(['message' => 'Deleted']);
    }

    public function download(FinalizedDocument $finalizedDocument): mixed
    {
        if (! Storage::disk('local')->exists($finalizedDocument->path)) {
            return response()->json([
                'message' => 'File not found on disk. This document may have been created from seed data without an actual file.',
            ], 404);
        }

        $ext = strtolower(pathinfo($finalizedDocument->path, PATHINFO_EXTENSION));
        $pdfName = pathinfo($finalizedDocument->name, PATHINFO_FILENAME) . '.pdf';

        if (in_array($ext, ['doc', 'docx'])) {
            try {
                $converter = app(\App\Services\DocToPdfConverter::class);
                $pdfPath = $converter->convertIfNeeded($finalizedDocument->path);
                if ($pdfPath && file_exists($pdfPath)) {
                    return response()->download($pdfPath, $pdfName, [
                        'Content-Type' => 'application/pdf',
                    ]);
                }
            } catch (\Throwable) {
                return response()->json(['message' => 'Could not generate PDF.'], 500);
            }
        }

        return response()->download(
            Storage::disk('local')->path($finalizedDocument->path),
            $ext === 'pdf' ? $finalizedDocument->name : $pdfName,
            ['Content-Type' => 'application/pdf']
        );
    }

    public function categories(): JsonResponse
    {
        return response()->json(FinalizedDocument::CATEGORIES);
    }
}
