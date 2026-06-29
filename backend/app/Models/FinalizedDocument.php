<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\UploadedFile;

class FinalizedDocument extends Model
{
    protected $fillable = [
        'user_id',
        'category',
        'name',
        'path',
        'mime_type',
        'size',
        'notes',
    ];

    public const CATEGORIES = [
        'licenses' => 'Company Licenses & Permits',
        'court_materials' => 'Court Materials',
        'corporate_docs' => 'Corporate Documents',
        'government_inspections' => 'Government Inspections',
        'other' => 'Other',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function storeUpload(int $userId, UploadedFile $file, string $category, ?string $notes = null): self
    {
        $path = $file->store('finalized-documents/'.$category, 'local');

        return self::create([
            'user_id' => $userId,
            'category' => $category,
            'name' => $file->getClientOriginalName(),
            'path' => $path,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'notes' => $notes,
        ]);
    }
}
