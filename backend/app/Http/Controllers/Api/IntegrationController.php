<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AdataService;
use App\Services\DocLogixImporter;
use App\Services\ParagraphService;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IntegrationController extends Controller
{
    /**
     * Legacy single-call (blocks up to 30s — may timeout).
     */
    public function adataCheck(string $bin): JsonResponse
    {
        $service = app(AdataService::class);
        return response()->json($service->checkReliability($bin));
    }

    /**
     * Step 1: Initiate an ADATA check — returns a polling token instantly.
     */
    public function adataInitiate(string $bin): JsonResponse
    {
        $service = app(AdataService::class);
        return response()->json($service->initiate($bin));
    }

    /**
     * Step 2: Poll for ADATA result using token.
     */
    public function adataCheckToken(Request $request): JsonResponse
    {
        $request->validate(['token' => 'required|string']);
        $service = app(AdataService::class);
        return response()->json($service->check($request->input('token')));
    }

    public function paragraphSearch(Request $request): JsonResponse
    {
        $query = $request->input('query', '');
        $service = app(ParagraphService::class);
        return response()->json($service->search($query));
    }

    public function doclogixImport(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:51200',
            'type' => 'required|in:partners,documents',
        ]);

        $importer = app(DocLogixImporter::class);
        $result = $importer->importFromCsv(
            $request->file('file'),
            $request->input('type'),
            $request->user()->id,
        );

        return response()->json($result);
    }

    public function esignStatus(): JsonResponse
    {
        $ncaEnabled = Setting::get('ncalayer_enabled', false);

        return response()->json([
            'ncalayer_enabled' => $ncaEnabled,
            'provider' => 'NCALayer',
            'supported_formats' => ['CMS', 'XML'],
            'websocket_url' => 'wss://127.0.0.1:13579',
            'message' => $ncaEnabled
                ? 'NCALayer digital signature is enabled.'
                : 'NCALayer is disabled. Enable in Admin > System Settings.',
        ]);
    }
}
