<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Models\PartnerDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PartnerDocumentController extends Controller
{
    public function index(Partner $partner): JsonResponse
    {
        return response()->json($partner->documents()->orderByDesc('created_at')->get());
    }

    public function store(Request $request, Partner $partner): JsonResponse
    {
        $request->validate([
            'document' => 'required|file|max:20480',
            'type' => 'nullable|string|in:vat_registration,charter,bank_certificate,power_of_attorney,license,id_document,contract,other',
        ]);

        $doc = PartnerDocument::storeUpload($partner, $request->file('document'), $request->input('type', 'other'));

        return response()->json($doc, 201);
    }

    public function destroy(Partner $partner, PartnerDocument $document): JsonResponse
    {
        if ($document->partner_id !== $partner->id) {
            abort(404);
        }

        Storage::disk('local')->delete($document->path);
        $document->delete();

        return response()->json(['message' => 'Deleted']);
    }

    public function download(Partner $partner, PartnerDocument $document): mixed
    {
        if ($document->partner_id !== $partner->id) {
            abort(404);
        }

        $path = Storage::disk('local')->path($document->path);
        if (! file_exists($path)) {
            abort(404);
        }

        return response()->download($path, $document->name);
    }
}
