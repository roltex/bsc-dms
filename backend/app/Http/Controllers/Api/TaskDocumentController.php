<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskDocument;
use App\Services\DocToPdfConverter;
use App\Services\TaskMainDocumentCommentMigrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TaskDocumentController extends Controller
{
    private function pdfFilename(Task $task): string
    {
        $task->loadMissing(['partner', 'category']);

        $partner = $task->partner?->name ?? 'unknown';
        $category = $task->category?->name ?? 'document';
        $date = $task->deadline?->format('d-m-Y') ?? now()->format('d-m-Y');

        return Str::slug($partner) . '-' . Str::slug($category) . '-' . $date . '.pdf';
    }

    public function upload(Request $request, Task $task): JsonResponse
    {
        $request->validate(['document' => 'required|file|mimes:doc,docx|max:20480']);

        $lastVersion = $task->documents()->max('version') ?? 0;
        $doc = TaskDocument::storeUpload($task, $request->file('document'), (int) $lastVersion + 1);

        app(TaskMainDocumentCommentMigrationService::class)->apply($task, $doc);

        TaskActivity::create([
            'task_id' => $task->id,
            'user_id' => $request->user()->id,
            'action' => 'document_uploaded',
            'meta' => ['document_id' => $doc->id, 'version' => $doc->version],
        ]);

        return response()->json($doc->fresh(), 201);
    }

    public function download(Task $task, TaskDocument $document): mixed
    {
        if ($document->task_id !== $task->id) {
            abort(404);
        }

        $path = Storage::disk('local')->path($document->path);
        if (! file_exists($path)) {
            abort(404);
        }

        $ext = strtolower(pathinfo($document->path, PATHINFO_EXTENSION));
        $filename = $this->pdfFilename($task);

        if (in_array($ext, ['doc', 'docx'])) {
            try {
                $converter = app(DocToPdfConverter::class);
                $pdfPath = $converter->convertIfNeeded($document->path);

                if ($pdfPath && file_exists($pdfPath)) {
                    return response()->download($pdfPath, $filename, [
                        'Content-Type' => 'application/pdf',
                    ]);
                }
            } catch (\Throwable) {
                abort(500, 'Could not generate PDF. Please contact support.');
            }
        }

        return response()->download($path, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function preview(Task $task, TaskDocument $document): mixed
    {
        if ($document->task_id !== $task->id) {
            abort(404);
        }

        $path = Storage::disk('local')->path($document->path);
        if (! file_exists($path)) {
            abort(404);
        }

        $ext = strtolower(pathinfo($document->path, PATHINFO_EXTENSION));

        if (in_array($ext, ['doc', 'docx'])) {
            try {
                $converter = app(DocToPdfConverter::class);
                $pdfPath = $converter->convertIfNeeded($document->path);

                if ($pdfPath && file_exists($pdfPath)) {
                    return response()->file($pdfPath, [
                        'Content-Type' => 'application/pdf',
                        'Content-Disposition' => 'inline; filename="'.$this->pdfFilename($task).'"',
                    ]);
                }
            } catch (\Throwable) {
                abort(500, 'Could not generate PDF preview.');
            }
        }

        return response()->file($path, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$this->pdfFilename($task).'"',
        ]);
    }

    public function uploadAttachments(Request $request, Task $task): JsonResponse
    {
        $request->validate([
            'files' => 'required|array|min:1',
            'files.*' => 'file|max:20480',
        ]);

        $currentStep = $task->workflowRoute
            ? $task->workflowRoute->steps()->where('sort_order', $task->current_step)->first()
            : null;

        if (! $currentStep || empty($currentStep->config['can_edit_attachments'])) {
            return response()->json(['message' => 'Attachment editing is not allowed at this step.'], 422);
        }

        $user = $request->user();
        $engine = app(\App\Services\WorkflowEngine::class);
        if (! $engine->canActOnStep($currentStep, $user, $task)) {
            return response()->json(['message' => 'You do not have permission to edit attachments at this step.'], 403);
        }

        $maxVersion = $task->documents()->where('is_attachment', true)->max('version') ?? 0;
        $newVersion = $maxVersion + 1;

        $existingDocs = $maxVersion > 0
            ? $task->documents()->where('is_attachment', true)->where('version', $maxVersion)->get()
            : collect();

        foreach ($existingDocs as $existing) {
            $task->documents()->create([
                'path' => $existing->path,
                'mime_type' => $existing->mime_type,
                'version' => $newVersion,
                'is_attachment' => true,
                'original_name' => $existing->original_name,
            ]);
        }

        $uploaded = [];
        foreach ($request->file('files') as $file) {
            $originalName = $file->getClientOriginalName();
            $path = $file->store('tasks/'.$task->id, 'local');

            $doc = $task->documents()->create([
                'path' => $path,
                'mime_type' => $file->getMimeType(),
                'version' => $newVersion,
                'is_attachment' => true,
                'original_name' => $originalName,
            ]);

            $uploaded[] = $doc;
        }

        $names = collect($uploaded)->pluck('original_name')->implode(', ');
        TaskActivity::create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'action' => 'attachment_updated',
            'comment' => "Added attachment(s): {$names} (v{$newVersion})",
            'meta' => ['version' => $newVersion, 'added_files' => collect($uploaded)->pluck('original_name')->toArray()],
        ]);

        return response()->json([
            'message' => count($uploaded).' file(s) added. Attachments updated to v'.$newVersion.'.',
            'attachments' => $uploaded,
            'version' => $newVersion,
        ], 201);
    }

    public function replaceAttachment(Request $request, Task $task, TaskDocument $document): JsonResponse
    {
        if ($document->task_id !== $task->id || ! $document->is_attachment) {
            return response()->json(['message' => 'Attachment not found.'], 404);
        }

        $request->validate([
            'file' => 'required|file|max:20480',
        ]);

        $currentStep = $task->workflowRoute
            ? $task->workflowRoute->steps()->where('sort_order', $task->current_step)->first()
            : null;

        if (! $currentStep || empty($currentStep->config['can_edit_attachments'])) {
            return response()->json(['message' => 'Attachment editing is not allowed at this step.'], 422);
        }

        $user = $request->user();
        $engine = app(\App\Services\WorkflowEngine::class);
        if (! $engine->canActOnStep($currentStep, $user, $task)) {
            return response()->json(['message' => 'You do not have permission to edit attachments at this step.'], 403);
        }

        $oldVersion = $document->version;
        $maxVersion = $task->documents()->where('is_attachment', true)->max('version') ?? 0;
        $newVersion = $maxVersion + 1;

        $siblingDocs = $task->documents()
            ->where('is_attachment', true)
            ->where('version', $oldVersion)
            ->where('id', '!=', $document->id)
            ->get();

        foreach ($siblingDocs as $sibling) {
            $task->documents()->create([
                'path' => $sibling->path,
                'mime_type' => $sibling->mime_type,
                'version' => $newVersion,
                'is_attachment' => true,
                'original_name' => $sibling->original_name,
            ]);
        }

        $file = $request->file('file');
        $path = $file->store('tasks/'.$task->id, 'local');
        $newOriginalName = $file->getClientOriginalName();
        $oldOriginalName = $document->original_name ?: 'unknown';

        $newDoc = $task->documents()->create([
            'path' => $path,
            'mime_type' => $file->getMimeType(),
            'version' => $newVersion,
            'is_attachment' => true,
            'original_name' => $newOriginalName,
        ]);

        TaskActivity::create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'action' => 'attachment_replaced',
            'comment' => "Replaced {$oldOriginalName} with {$newOriginalName} (v{$oldVersion} → v{$newVersion})",
            'meta' => [
                'old_document_id' => $document->id,
                'new_document_id' => $newDoc->id,
                'old_filename' => $oldOriginalName,
                'new_filename' => $newOriginalName,
                'version' => $newVersion,
            ],
        ]);

        return response()->json(['message' => "Replaced {$oldOriginalName} with {$newOriginalName}. Attachments v{$newVersion}.", 'version' => $newVersion], 201);
    }

    public function deleteAttachment(Request $request, Task $task, TaskDocument $document): JsonResponse
    {
        if ($document->task_id !== $task->id || ! $document->is_attachment) {
            return response()->json(['message' => 'Attachment not found.'], 404);
        }

        $currentStep = $task->workflowRoute
            ? $task->workflowRoute->steps()->where('sort_order', $task->current_step)->first()
            : null;

        if (! $currentStep || empty($currentStep->config['can_edit_attachments'])) {
            return response()->json(['message' => 'Attachment editing is not allowed at this step.'], 422);
        }

        $user = $request->user();
        $engine = app(\App\Services\WorkflowEngine::class);
        if (! $engine->canActOnStep($currentStep, $user, $task)) {
            return response()->json(['message' => 'You do not have permission to delete attachments at this step.'], 403);
        }

        $originalName = $document->original_name ?: basename($document->path);
        $oldVersion = $document->version;
        $maxVersion = $task->documents()->where('is_attachment', true)->max('version') ?? 0;
        $newVersion = $maxVersion + 1;

        $siblingDocs = $task->documents()
            ->where('is_attachment', true)
            ->where('version', $oldVersion)
            ->where('id', '!=', $document->id)
            ->get();

        foreach ($siblingDocs as $sibling) {
            $task->documents()->create([
                'path' => $sibling->path,
                'mime_type' => $sibling->mime_type,
                'version' => $newVersion,
                'is_attachment' => true,
                'original_name' => $sibling->original_name,
            ]);
        }

        TaskActivity::create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'action' => 'attachment_deleted',
            'comment' => "Removed attachment: {$originalName} (v{$oldVersion} → v{$newVersion})",
            'meta' => ['filename' => $originalName, 'old_version' => $oldVersion, 'new_version' => $newVersion],
        ]);

        return response()->json(['message' => 'Attachment removed. New version v'.$newVersion.' created.', 'version' => $newVersion]);
    }

    public function downloadAttachment(Task $task, TaskDocument $document): mixed
    {
        if ($document->task_id !== $task->id) {
            abort(404);
        }

        $path = Storage::disk('local')->path($document->path);
        if (! file_exists($path)) {
            abort(404);
        }

        $filename = $document->original_name ?: basename($document->path);

        return response()->download($path, $filename, [
            'Content-Type' => $document->mime_type ?: mime_content_type($path),
        ]);
    }
}
