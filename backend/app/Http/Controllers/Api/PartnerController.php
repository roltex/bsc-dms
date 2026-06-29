<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePartnerRequest;
use App\Http\Requests\UpdatePartnerRequest;
use App\Models\Partner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PartnerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Partner::query()->with('documents');

        if ($request->filled('search')) {
            $s = '%'.$request->input('search').'%';
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', $s)
                    ->orWhere('bin_iin', 'like', $s);
            });
        }

        if ($request->boolean('blacklisted')) {
            $query->whereNotNull('blacklisted_at');
        }

        $partners = $query->orderBy('name')->paginate(
            $request->integer('per_page', 15)
        );

        return response()->json($partners);
    }

    public function store(StorePartnerRequest $request): JsonResponse
    {
        $partner = Partner::create($request->validated());

        try {
            $sap = app(\App\Services\SapService::class);
            if ($sap->isEnabled()) {
                $sap->syncPartner($partner);
            }
        } catch (\Throwable $e) {
            \Log::warning('SAP partner sync failed: ' . $e->getMessage());
        }

        return response()->json($partner->load('documents'), 201);
    }

    public function show(Partner $partner): JsonResponse
    {
        $partner->load('documents', 'blacklistedByUser');

        return response()->json($partner);
    }

    public function update(UpdatePartnerRequest $request, Partner $partner): JsonResponse
    {
        $partner->update($request->validated());

        try {
            $sap = app(\App\Services\SapService::class);
            if ($sap->isEnabled()) {
                $sap->syncPartner($partner);
            }
        } catch (\Throwable $e) {
            \Log::warning('SAP partner sync failed: ' . $e->getMessage());
        }

        return response()->json($partner->fresh('documents'));
    }

    public function destroy(Partner $partner): JsonResponse
    {
        $partner->delete();

        return response()->json(null, 204);
    }

    public function checkBinIin(Request $request): JsonResponse
    {
        $request->validate(['bin_iin' => 'required|string|max:20']);

        $exists = Partner::where('bin_iin', $request->input('bin_iin'))->exists();

        return response()->json(['exists' => $exists]);
    }

    public function blacklist(Request $request, Partner $partner): JsonResponse
    {
        $request->validate(['reason' => 'required|string|max:1000']);

        $partner->update([
            'blacklisted_at' => now(),
            'blacklist_reason' => $request->input('reason'),
            'blacklisted_by' => $request->user()->id,
        ]);

        return response()->json($partner->fresh('documents', 'blacklistedByUser'));
    }

    public function unblacklist(Partner $partner): JsonResponse
    {
        $partner->update([
            'blacklisted_at' => null,
            'blacklist_reason' => null,
            'blacklisted_by' => null,
        ]);

        return response()->json($partner->fresh('documents'));
    }
}
