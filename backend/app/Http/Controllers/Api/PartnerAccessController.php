<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PartnerAccessToken;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskDocument;
use App\Services\DocToPdfConverter;
use App\Services\TaskMainDocumentCommentMigrationService;
use App\Services\WorkflowEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PartnerAccessController extends Controller
{
    private function pdfFilename(Task $task): string
    {
        $task->loadMissing(['partner', 'category']);

        $partner = $task->partner?->name ?? 'unknown';
        $category = $task->category?->name ?? 'document';
        $date = $task->deadline?->format('d-m-Y') ?? now()->format('d-m-Y');

        return Str::slug($partner) . '-' . Str::slug($category) . '-' . $date . '.pdf';
    }

    public function show(string $token): JsonResponse
    {
        $access = $this->resolveTokenForRead($token);
        if ($access instanceof JsonResponse) {
            return $access;
        }

        $task = $access->task->load([
            'category', 'partner', 'initiator:id,name',
            'documents', 'activities.user', 'workflowRoute.steps',
        ]);

        $step = $access->workflowStep;

        $canAct = ! $access->used_at
            && ! $access->expires_at->isPast()
            && $task->current_step === $step->sort_order;

        return response()->json([
            'task' => [
                'id' => $task->id,
                'status' => $task->status,
                'current_step' => $task->current_step,
                'commercial_terms' => $task->commercial_terms,
                'deadline' => $task->deadline,
                'validity_from' => $task->validity_from,
                'validity_to' => $task->validity_to,
                'category' => $task->category,
                'partner' => $task->partner,
                'initiator' => ['name' => $task->initiator?->name],
                'documents' => $task->documents->map(fn ($d) => [
                    'id' => $d->id,
                    'version' => $d->version,
                    'is_signed' => $d->is_signed,
                    'created_at' => $d->created_at,
                ]),
                'activities' => $task->activities->map(fn ($a) => [
                    'action' => $a->action,
                    'comment' => $a->comment,
                    'created_at' => $a->created_at,
                    'user' => $a->user ? ['name' => $a->user->name] : null,
                ]),
                'workflow_route' => $task->workflowRoute ? [
                    'name' => $task->workflowRoute->name,
                    'steps' => $task->workflowRoute->steps->map(fn ($s) => [
                        'sort_order' => $s->sort_order,
                        'name' => $s->name,
                        'role' => $s->role,
                        'action_type' => $s->action_type,
                    ]),
                ] : null,
            ],
            'step' => [
                'id' => $step->id,
                'name' => $step->name,
                'role' => $step->role,
                'action_type' => $step->action_type,
            ],
            'partner_name' => $access->partner->name,
            'expires_at' => $access->expires_at,
            'can_act' => $canAct,
            'action_taken' => $access->action_taken,
            'available_actions' => $canAct ? array_values(array_unique(array_merge(
                app(WorkflowEngine::class)->getAvailableOutcomes($task),
                ['rejected']
            ))) : [],
        ]);
    }

    public function downloadDocument(string $token, int $documentId): mixed
    {
        $access = $this->resolveTokenForRead($token);
        if ($access instanceof JsonResponse) {
            return $access;
        }

        $doc = TaskDocument::where('task_id', $access->task_id)->findOrFail($documentId);

        if (! Storage::disk('local')->exists($doc->path)) {
            return response()->json(['message' => 'File not found.'], 404);
        }

        $ext = strtolower(pathinfo($doc->path, PATHINFO_EXTENSION));
        $task = $access->task;
        $filename = $this->pdfFilename($task);

        if (in_array($ext, ['doc', 'docx'])) {
            try {
                $converter = app(\App\Services\DocToPdfConverter::class);
                $pdfPath = $converter->convertIfNeeded($doc->path);
                if ($pdfPath && file_exists($pdfPath)) {
                    return response()->download($pdfPath, $filename, [
                        'Content-Type' => 'application/pdf',
                    ]);
                }
            } catch (\Throwable) {
                return response()->json(['message' => 'Could not generate PDF.'], 500);
            }
        }

        return response()->download(
            Storage::disk('local')->path($doc->path),
            $filename,
            ['Content-Type' => 'application/pdf']
        );
    }

    public function previewDocument(string $token, int $documentId): mixed
    {
        $access = $this->resolveTokenForRead($token);
        if ($access instanceof JsonResponse) {
            return $access;
        }

        $doc = TaskDocument::where('task_id', $access->task_id)->findOrFail($documentId);

        if (! Storage::disk('local')->exists($doc->path)) {
            return response()->json(['message' => 'File not found.'], 404);
        }

        $converter = app(DocToPdfConverter::class);
        $pdfAbsPath = $converter->convertIfNeeded($doc->path);

        if (! file_exists($pdfAbsPath)) {
            return response()->json(['message' => 'Could not generate preview.'], 500);
        }

        return response()->file($pdfAbsPath, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$this->pdfFilename($access->task).'"',
        ]);
    }

    public function approve(Request $request, string $token): JsonResponse
    {
        $access = $this->resolveToken($token);
        if ($access instanceof JsonResponse) {
            return $access;
        }

        $task = $access->task;
        $step = $access->workflowStep;

        if ($task->current_step !== $step->sort_order) {
            return response()->json(['message' => 'This step is no longer active.'], 422);
        }

        $comment = $request->input('comment', 'Approved by partner: '.$access->partner->name);

        $engine = app(WorkflowEngine::class);
        $result = $engine->advanceAsPartner($task, $access, 'approved', $comment);

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], 422);
        }

        $access->update([
            'used_at' => now(),
            'action_taken' => 'approved',
            'comment' => $comment,
        ]);

        return response()->json(['message' => 'Task approved successfully.', 'result' => $result]);
    }

    public function reject(Request $request, string $token): JsonResponse
    {
        $access = $this->resolveToken($token);
        if ($access instanceof JsonResponse) {
            return $access;
        }

        $request->validate(['comment' => 'required|string|max:2000']);

        $task = $access->task;
        $step = $access->workflowStep;

        if ($task->current_step !== $step->sort_order) {
            return response()->json(['message' => 'This step is no longer active.'], 422);
        }

        $comment = 'Rejected by partner ('.$access->partner->name.'): '.$request->input('comment');

        $engine = app(WorkflowEngine::class);
        $result = $engine->advanceAsPartner($task, $access, 'rejected', $comment);

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], 422);
        }

        $access->update([
            'used_at' => now(),
            'action_taken' => 'rejected',
            'comment' => $request->input('comment'),
        ]);

        return response()->json(['message' => 'Task rejected.', 'result' => $result]);
    }

    public function uploadDocument(Request $request, string $token): JsonResponse
    {
        $access = $this->resolveToken($token);
        if ($access instanceof JsonResponse) {
            return $access;
        }

        $request->validate([
            'document' => 'required|file|mimes:doc,docx,pdf|max:20480',
            'advance' => 'sometimes|boolean',
        ]);

        $task = $access->task;
        $step = $access->workflowStep;

        if ($task->current_step !== $step->sort_order) {
            return response()->json(['message' => 'This step is no longer active.'], 422);
        }

        $lastVersion = $task->documents()->max('version') ?? 0;
        $doc = TaskDocument::storeUpload($task, $request->file('document'), (int) $lastVersion + 1);

        app(TaskMainDocumentCommentMigrationService::class)->apply($task, $doc);

        TaskActivity::create([
            'task_id' => $task->id,
            'user_id' => null,
            'action' => 'partner_document_uploaded',
            'comment' => "Document uploaded by partner: {$access->partner->name}",
            'meta' => ['partner_id' => $access->partner_id, 'document_id' => $doc->id],
        ]);

        $result = null;
        if ($request->boolean('advance', true) && $step->action_type === 'upload_document') {
            $engine = app(WorkflowEngine::class);
            $result = $engine->advanceAsPartner(
                $task,
                $access,
                'approved',
                "Document uploaded and submitted by partner: {$access->partner->name}"
            );

            if ($result['success'] ?? false) {
                $access->update([
                    'used_at' => now(),
                    'action_taken' => 'uploaded',
                    'comment' => "Document uploaded by partner: {$access->partner->name}",
                ]);
            }
        }

        return response()->json([
            'message' => 'Document uploaded successfully.',
            'document' => $doc->fresh(),
            'result' => $result,
        ], 201);
    }

    public function uploadSigned(Request $request, string $token): JsonResponse
    {
        $access = $this->resolveToken($token);
        if ($access instanceof JsonResponse) {
            return $access;
        }

        $request->validate([
            'signature' => 'required|string',
        ]);

        $task = $access->task;
        $step = $access->workflowStep;

        if ($task->current_step !== $step->sort_order || $step->action_type !== 'sign') {
            return response()->json(['message' => 'Signing is not available at this step.'], 422);
        }

        $latestDoc = $task->documents()->orderByDesc('version')->first();
        if (! $latestDoc) {
            return response()->json(['message' => 'No document found to sign.'], 422);
        }

        $originalAbsPath = Storage::disk('local')->path($latestDoc->path);
        if (! file_exists($originalAbsPath)) {
            return response()->json(['message' => 'Document file not found.'], 404);
        }

        $lastVersion = $task->documents()->max('version') ?? 0;
        $newVersion = $lastVersion + 1;
        $dir = "tasks/{$task->id}";
        Storage::disk('local')->makeDirectory($dir);
        $srcExt = strtolower(pathinfo($latestDoc->path, PATHINFO_EXTENSION));
        $newRelPath = "{$dir}/signed-partner-v{$newVersion}.pdf";
        $newAbsPath = Storage::disk('local')->path($newRelPath);

        $signaturePath = $this->storeSignatureImage($task, $newVersion, $request->input('signature'));
        if (! $signaturePath) {
            return response()->json(['message' => 'Could not save signature.'], 422);
        }

        $stamper = app(\App\Services\SignatureStamper::class);
        $sigAbsPath = Storage::disk('local')->path($signaturePath);

        $pdfAbsPath = $srcExt === 'pdf' ? $originalAbsPath : preg_replace('/\.(docx?)$/i', '.pdf', $originalAbsPath);
        if (! file_exists($pdfAbsPath) && $srcExt !== 'pdf') {
            $converter = app(\App\Services\DocToPdfConverter::class);
            $root = rtrim(str_replace('\\', '/', Storage::disk('local')->path('')), '/');
            $origNorm = str_replace('\\', '/', $originalAbsPath);
            $converter->convertIfNeeded(ltrim(str_replace($root, '', $origNorm), '/'));
        }

        $stampedAbsPath = $stamper->stampAtPlaceholderAndConvert($pdfAbsPath, $sigAbsPath, '{{PARTNER_SIGN}}');

        if (! $stampedAbsPath || ! file_exists($stampedAbsPath)) {
            return response()->json(['message' => 'Could not stamp signature on document.'], 500);
        }

        $normalize = fn (string $p) => str_replace('\\', '/', rtrim($p, '\\/'));
        $root = $normalize(Storage::disk('local')->path(''));
        $stamped = $normalize($stampedAbsPath);
        $finalRelPath = ltrim(str_replace($root, '', $stamped), '/');

        $doc = $task->documents()->create([
            'path' => $finalRelPath,
            'mime_type' => 'application/pdf',
            'version' => $newVersion,
            'is_signed' => true,
            'signature_path' => $signaturePath,
        ]);

        app(TaskMainDocumentCommentMigrationService::class)->apply($task, $doc);

        $engine = app(WorkflowEngine::class);
        $result = $engine->advanceAsPartner($task, $access, 'approved', "Document signed by partner: {$access->partner->name}");

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], 422);
        }

        $access->update([
            'used_at' => now(),
            'action_taken' => 'signed',
            'comment' => "Document signed by partner: {$access->partner->name}",
        ]);

        return response()->json(['message' => 'Document signed successfully.', 'document' => $doc->fresh(), 'result' => $result], 201);
    }

    /**
     * Resolve token for read-only operations (show, download, preview).
     * Allows used tokens so the partner can still view the signed document.
     */
    private function resolveTokenForRead(string $token): PartnerAccessToken|JsonResponse
    {
        $access = PartnerAccessToken::with(['task', 'partner', 'workflowStep'])->where('token', $token)->first();

        if (! $access) {
            return response()->json(['message' => 'Invalid access link.'], 404);
        }

        return $access;
    }

    /**
     * Resolve token for write operations (sign, reject, upload).
     * Blocks used and expired tokens.
     */
    private function resolveToken(string $token): PartnerAccessToken|JsonResponse
    {
        $access = PartnerAccessToken::with(['task', 'partner', 'workflowStep'])->where('token', $token)->first();

        if (! $access) {
            return response()->json(['message' => 'Invalid or expired access link.'], 404);
        }

        if ($access->used_at) {
            return response()->json([
                'message' => 'This link has already been used.',
                'action_taken' => $access->action_taken,
            ], 410);
        }

        if ($access->expires_at->isPast()) {
            return response()->json(['message' => 'This link has expired.'], 410);
        }

        return $access;
    }

    private function storeSignatureImage($task, $versionOrDoc, string $base64): ?string
    {
        if (! preg_match('/^data:image\/png;base64,(.+)$/', $base64, $matches)) {
            return null;
        }

        $imageData = base64_decode($matches[1], true);
        if ($imageData === false) {
            return null;
        }

        $version = is_object($versionOrDoc) ? $versionOrDoc->version : $versionOrDoc;
        $dir = "signatures/task-{$task->id}";
        $filename = "partner-sig-v{$version}-".time().'.png';
        Storage::disk('local')->makeDirectory($dir);
        Storage::disk('local')->put("{$dir}/{$filename}", $imageData);

        return "{$dir}/{$filename}";
    }
}
