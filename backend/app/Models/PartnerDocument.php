<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class PartnerDocument extends Model
{
    protected $fillable = [
        'partner_id',
        'name',
        'path',
        'mime_type',
        'size',
        'type',
    ];

    public const DOCUMENT_TYPES = [
        'vat_registration' => 'VAT Registration',
        'charter' => 'Company Charter',
        'bank_certificate' => 'Bank Certificate',
        'power_of_attorney' => 'Power of Attorney',
        'license' => 'License',
        'id_document' => 'ID Document',
        'contract' => 'Contract',
        'other' => 'Other',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public static function storeUpload(Partner $partner, UploadedFile $file, string $type = 'other'): self
    {
        $path = $file->store('partner-documents/'.$partner->id, 'local');

        return $partner->documents()->create([
            'name' => $file->getClientOriginalName(),
            'path' => $path,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'type' => $type,
        ]);
    }

    public function getStoragePath(): string
    {
        return Storage::disk('local')->path($this->path);
    }
}
