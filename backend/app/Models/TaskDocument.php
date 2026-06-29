<?php

namespace App\Models;

use App\Services\DocToPdfConverter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class TaskDocument extends Model
{
    protected $fillable = [
        'task_id',
        'path',
        'mime_type',
        'version',
        'is_attachment',
        'original_name',
        'approved_at',
        'registration_number',
        'is_signed',
        'signature_path',
        'signed_by',
    ];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'is_signed' => 'boolean',
            'is_attachment' => 'boolean',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function signer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signed_by');
    }

    public static function storeUpload(Task $task, UploadedFile $file, int $version = 1, bool $isAttachment = false): self
    {
        $originalName = $file->getClientOriginalName();
        $path = $file->store('tasks/'.$task->id, 'local');
        $mime = $file->getMimeType();
        $ext = strtolower($file->getClientOriginalExtension() ?: pathinfo($path, PATHINFO_EXTENSION));

        if (!$isAttachment && in_array($ext, ['doc', 'docx'])) {
            try {
                $converter = app(DocToPdfConverter::class);
                $pdfAbsPath = $converter->convertIfNeeded($path);
                if ($pdfAbsPath && file_exists($pdfAbsPath)) {
                    Storage::disk('local')->delete($path);
                    $path = preg_replace('/\.(docx?|rtf)$/i', '.pdf', $path);
                    $mime = 'application/pdf';
                }
            } catch (\Throwable) {
                // Keep original if conversion fails
            }
        }

        return $task->documents()->create([
            'path' => $path,
            'mime_type' => $mime,
            'version' => $version,
            'is_attachment' => $isAttachment,
            'original_name' => $originalName,
        ]);
    }
}
