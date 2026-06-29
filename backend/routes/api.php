<?php

use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\AiChatController;
use App\Http\Controllers\Api\ArchiveController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DocumentCategoryController;
use App\Http\Controllers\Api\DocumentCompareController;
use App\Http\Controllers\Api\DocumentTemplateController;
use App\Http\Controllers\Api\FinalizedDocumentController;
use App\Http\Controllers\Api\IntegrationController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PartnerAccessController;
use App\Http\Controllers\Api\InventoryItemController;
use App\Http\Controllers\Api\PartnerController;
use App\Http\Controllers\Api\PartnerDocumentController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\SubstitutionController;
use App\Http\Controllers\Api\TaskCommentController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\TaskDocumentController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;

Route::get('/health', HealthController::class);

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    $user = $request->user();

    return response()->json([
        'id' => $user->id,
        'name' => $user->name,
        'email' => $user->email,
        'role' => $user->role->value,
        'email_verified_at' => $user->email_verified_at?->toIso8601String(),
        'created_at' => $user->created_at->toIso8601String(),
        'updated_at' => $user->updated_at->toIso8601String(),
    ]);
});

Route::middleware('throttle:5,1')->post('/login', function (Request $request) {
    $request->validate([
        'email' => 'required|email',
        'password' => 'required',
    ]);

    if (! Auth::attempt($request->only('email', 'password'), $request->boolean('remember'))) {
        throw ValidationException::withMessages([
            'email' => [__('auth.failed')],
        ]);
    }

    $request->session()->regenerate();
    $user = $request->user();

    return response()->json([
        'id' => $user->id,
        'name' => $user->name,
        'email' => $user->email,
        'role' => $user->role->value,
        'email_verified_at' => $user->email_verified_at?->toIso8601String(),
        'created_at' => $user->created_at->toIso8601String(),
        'updated_at' => $user->updated_at->toIso8601String(),
    ]);
});

Route::middleware('auth:sanctum')->post('/logout', function (Request $request) {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return response()->json(['message' => 'Logged out']);
});

// Public branding settings (no auth - needed by login page)
Route::get('/branding', function () {
    return response()->json([
        'app_name' => (string) \App\Models\Setting::get('app_name', 'DMS'),
        'company_name' => (string) \App\Models\Setting::get('company_name', ''),
    ]);
})->middleware('throttle:30,1');

// Partner access (public, token-based, no login required)
Route::prefix('partner-access/{token}')->group(function () {
    Route::get('/', [PartnerAccessController::class, 'show'])->name('partner-access.show');
    Route::get('/documents/{document}/download', [PartnerAccessController::class, 'downloadDocument'])->name('partner-access.download');
    Route::get('/documents/{document}/preview', [PartnerAccessController::class, 'previewDocument'])->name('partner-access.preview');
    Route::post('/approve', [PartnerAccessController::class, 'approve'])->name('partner-access.approve');
    Route::post('/reject', [PartnerAccessController::class, 'reject'])->name('partner-access.reject');
    Route::post('/upload-document', [PartnerAccessController::class, 'uploadDocument'])->name('partner-access.upload');
    Route::post('/sign', [PartnerAccessController::class, 'uploadSigned'])->name('partner-access.sign');
});

// Google OAuth callback (public - Google redirects here)
Route::get('/google/callback', [SettingController::class, 'googleCallback'])->name('google.callback');

Route::middleware('auth:sanctum')->group(function () {
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // AI Chat Assistant
    Route::post('/ai-chat', [AiChatController::class, 'chat'])->middleware('throttle:20,1')->name('ai-chat');
    Route::post('/ai-chat/generate-document', [AiChatController::class, 'generateDocument'])->name('ai-chat.generate-document');

    // Substitutions
    Route::get('/substitutions', [SubstitutionController::class, 'index'])->name('substitutions.index');

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread-count');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy'])->name('notifications.destroy');
    Route::delete('/notifications', [NotificationController::class, 'destroyAll'])->name('notifications.destroy-all');

    // Archive
    Route::get('/archive', [ArchiveController::class, 'index'])->name('archive.index');
    Route::get('/archive/export', [ArchiveController::class, 'export'])->name('archive.export');

    // Document compare
    Route::post('/documents/compare', [DocumentCompareController::class, 'compare'])->name('documents.compare');
    Route::post('/documents/analyze', [DocumentCompareController::class, 'analyze'])->name('documents.analyze');
    Route::post('/documents/validate', [DocumentCompareController::class, 'validate'])->name('documents.validate');

    // Document categories & templates
    Route::get('/document-categories', [DocumentCategoryController::class, 'index'])->name('document-categories.index');
    Route::get('/document-templates', [DocumentTemplateController::class, 'index'])->name('document-templates.index');
    Route::post('/document-templates/upload-custom', [DocumentTemplateController::class, 'uploadCustom'])->name('document-templates.upload-custom');
    Route::get('/document-templates/{template}/download', [DocumentTemplateController::class, 'download'])->name('document-templates.download');
    Route::post('/document-templates/{template}/preview', [DocumentTemplateController::class, 'preview'])->name('document-templates.preview');
    Route::post('/document-templates/{template}/content', [DocumentTemplateController::class, 'content'])->name('document-templates.content');
    Route::post('/document-templates/{template}/preview-html', [DocumentTemplateController::class, 'previewHtml'])->name('document-templates.preview-html');
    Route::post('/document-templates/{template}/google-edit', [DocumentTemplateController::class, 'googleEdit'])->name('document-templates.google-edit');
    Route::post('/document-templates/{template}/google-sync', [DocumentTemplateController::class, 'googleSync'])->name('document-templates.google-sync');
    Route::get('/document-templates/{template}/tables', [DocumentTemplateController::class, 'tables'])->name('document-templates.tables');

    // Template variables reference
    Route::get('/template-variables', function () {
        return response()->json(\App\Services\TemplateVariableRegistry::all());
    })->name('template-variables.index');

    // Inventory Items
    Route::apiResource('inventory-items', InventoryItemController::class);

    // Partners
    Route::get('/partners/check-bin-iin', [PartnerController::class, 'checkBinIin'])->name('partners.check-bin-iin');
    Route::apiResource('partners', PartnerController::class);
    Route::post('/partners/{partner}/blacklist', [PartnerController::class, 'blacklist'])
        ->middleware(\App\Http\Middleware\EnsureUserIsLawyer::class)
        ->name('partners.blacklist');
    Route::post('/partners/{partner}/unblacklist', [PartnerController::class, 'unblacklist'])
        ->middleware(\App\Http\Middleware\EnsureUserIsLawyer::class)
        ->name('partners.unblacklist');

    // Partner documents
    Route::get('/partner-document-types', function () {
        return response()->json(\App\Models\PartnerDocument::DOCUMENT_TYPES);
    })->name('partner-document-types');
    Route::get('/partners/{partner}/documents', [PartnerDocumentController::class, 'index'])->name('partners.documents.index');
    Route::post('/partners/{partner}/documents', [PartnerDocumentController::class, 'store'])->name('partners.documents.store');
    Route::delete('/partners/{partner}/documents/{document}', [PartnerDocumentController::class, 'destroy'])->name('partners.documents.destroy');
    Route::get('/partners/{partner}/documents/{document}/download', [PartnerDocumentController::class, 'download'])->name('partners.documents.download');

    // Tasks
    Route::apiResource('tasks', TaskController::class)->only(['index', 'store', 'show', 'update']);
    Route::post('/tasks/{task}/submit', [TaskController::class, 'submit'])->name('tasks.submit');
    Route::post('/tasks/{task}/approve', [TaskController::class, 'approve'])->name('tasks.approve');
    Route::post('/tasks/{task}/reject', [TaskController::class, 'reject'])->name('tasks.reject');
    Route::post('/tasks/{task}/delegate', [TaskController::class, 'delegate'])->name('tasks.delegate');
    Route::post('/tasks/{task}/fast-track', [TaskController::class, 'fastTrack'])->name('tasks.fast-track');
    Route::post('/tasks/{task}/reviewers', [TaskController::class, 'addReviewer'])->name('tasks.reviewers.store');
    Route::delete('/tasks/{task}/reviewers/{reviewer}', [TaskController::class, 'removeReviewer'])->name('tasks.reviewers.destroy');
    Route::post('/tasks/{task}/signed-document', [TaskController::class, 'uploadSigned'])->name('tasks.signed-document');
    Route::post('/tasks/{task}/eds-sign', [TaskController::class, 'uploadEds'])->name('tasks.eds-sign');
    Route::post('/tasks/{task}/return', [TaskController::class, 'returnForRevision'])->name('tasks.return');
    Route::post('/tasks/{task}/upload-final', [TaskController::class, 'uploadFinalVersion'])->name('tasks.upload-final');
    Route::post('/tasks/{task}/upload-document-step', [TaskController::class, 'uploadDocumentStep'])->name('tasks.upload-document-step');
    Route::get('/tasks/{task}/document-content', [TaskController::class, 'getDocumentContent'])->name('tasks.document-content');
    Route::put('/tasks/{task}/document-content', [TaskController::class, 'saveDocumentContent'])->name('tasks.document-content.save');
    Route::get('/tasks/{task}/summary-report', [TaskController::class, 'summaryReport'])->name('tasks.summary-report');
    Route::get('/tasks/{task}/available-actions', [TaskController::class, 'availableActions'])->name('tasks.available-actions');
    Route::post('/tasks/{task}/google-edit', [TaskController::class, 'googleEdit'])->name('tasks.google-edit');
    Route::post('/tasks/{task}/google-sync', [TaskController::class, 'googleSync'])->name('tasks.google-sync');

    // Task comments
    Route::get('/tasks/{task}/comments', [TaskCommentController::class, 'index'])->name('tasks.comments.index');
    Route::post('/tasks/{task}/comments', [TaskCommentController::class, 'store'])->name('tasks.comments.store');
    Route::patch('/tasks/{task}/comments/{comment}', [TaskCommentController::class, 'update'])->name('tasks.comments.update');
    Route::delete('/tasks/{task}/comments/{comment}', [TaskCommentController::class, 'destroy'])->name('tasks.comments.destroy');

    // Task documents
    Route::post('/tasks/{task}/documents', [TaskDocumentController::class, 'upload'])->name('tasks.documents.upload');
    Route::get('/tasks/{task}/documents/{document}/download', [TaskDocumentController::class, 'download'])->name('tasks.documents.download');
    Route::get('/tasks/{task}/documents/{document}/preview', [TaskDocumentController::class, 'preview'])->name('tasks.documents.preview');
    Route::get('/tasks/{task}/documents/{document}/signature', [TaskController::class, 'signature'])->name('tasks.documents.signature');
    Route::get('/tasks/{task}/attachments/{document}/download', [TaskDocumentController::class, 'downloadAttachment'])->name('tasks.attachments.download');
    Route::post('/tasks/{task}/attachments/upload', [TaskDocumentController::class, 'uploadAttachments'])->name('tasks.attachments.upload');
    Route::post('/tasks/{task}/attachments/{document}/replace', [TaskDocumentController::class, 'replaceAttachment'])->name('tasks.attachments.replace');
    Route::delete('/tasks/{task}/attachments/{document}', [TaskDocumentController::class, 'deleteAttachment'])->name('tasks.attachments.delete');

    // Finalized documents (no approval flow)
    Route::get('/finalized-documents/categories', [FinalizedDocumentController::class, 'categories'])->name('finalized-documents.categories');
    Route::apiResource('finalized-documents', FinalizedDocumentController::class)->only(['index', 'store', 'show', 'destroy']);
    Route::get('/finalized-documents/{finalized_document}/download', [FinalizedDocumentController::class, 'download'])->name('finalized-documents.download');

    // Integration stubs
    Route::get('/integrations/adata/check/{bin}', [IntegrationController::class, 'adataCheck'])->name('integrations.adata.check');
    Route::get('/integrations/adata/initiate/{bin}', [IntegrationController::class, 'adataInitiate'])->name('integrations.adata.initiate');
    Route::get('/integrations/adata/poll', [IntegrationController::class, 'adataCheckToken'])->name('integrations.adata.poll');
    Route::get('/integrations/paragraph/search', [IntegrationController::class, 'paragraphSearch'])->name('integrations.paragraph.search');
    Route::post('/integrations/doclogix/import', [IntegrationController::class, 'doclogixImport'])->name('integrations.doclogix.import');
    Route::get('/integrations/esign/status', [IntegrationController::class, 'esignStatus'])->name('integrations.esign.status');

    // Workflow routes
    Route::get('/workflow-routes', function (\Illuminate\Http\Request $request) {
        $query = \App\Models\WorkflowRoute::where('is_active', true)
            ->with(['steps' => fn ($q) => $q->orderBy('sort_order')]);

        if ($categoryId = $request->query('document_category_id')) {
            $query->whereHas('documentCategories', fn ($q) => $q->where('document_categories.id', $categoryId));
        }

        $data = $query->orderBy('name')
            ->get()
            ->map(fn ($route) => [
                'id' => $route->id,
                'name' => $route->name,
                'slug' => $route->slug,
                'description' => $route->description,
                'is_default' => $route->is_default,
                'category_ids' => $route->documentCategories()->pluck('document_categories.id'),
                'steps' => $route->steps->map(fn ($s) => [
                    'id' => $s->id,
                    'sort_order' => $s->sort_order,
                    'name' => $s->name,
                    'role' => $s->role,
                    'action_type' => $s->action_type,
                    'duration_days' => (int) ($s->duration_days ?? 1),
                ]),
            ]);
        return response()->json($data)->header('Cache-Control', 'no-cache, no-store, must-revalidate');
    })->name('workflow-routes.index');

    // Settings
    Route::get('/settings/google-status', [SettingController::class, 'googleStatus'])->name('settings.google-status');
    Route::get('/settings/google', [SettingController::class, 'getGoogleSettings'])->name('settings.google');
    Route::put('/settings/google', [SettingController::class, 'saveGoogleSettings'])->name('settings.google.save');
    Route::get('/settings/google-auth-url', [SettingController::class, 'googleAuthUrl'])->name('settings.google-auth-url');
    Route::post('/settings/google-disconnect', [SettingController::class, 'googleDisconnect'])->name('settings.google-disconnect');

    // Users list (for lawyer delegation, reviewer selection)
    Route::get('/users', function (Request $request) {
        $query = \App\Models\User::query()->select('id', 'name', 'email', 'role');
        if ($request->filled('role')) {
            $query->where('role', $request->input('role'));
        }
        return response()->json($query->orderBy('name')->get());
    })->name('users.index');
});
