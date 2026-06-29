<?php

namespace App\Services;

use App\Models\Partner;
use App\Models\Setting;
use App\Models\Task;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SapService
{
    public function isEnabled(): bool
    {
        return (bool) Setting::get('sap_sync_enabled', false)
            && !empty(Setting::get('sap_api_url', ''))
            && !empty(Setting::get('sap_api_key', ''));
    }

    public function syncPartner(Partner $partner): array
    {
        if (! $this->isEnabled()) {
            return ['status' => 'disabled', 'message' => 'SAP sync is not enabled.'];
        }

        try {
            $response = $this->request()->post('/partners', [
                'vendor_id' => $partner->bin_iin,
                'name' => $partner->name,
                'bin_iin' => $partner->bin_iin,
                'email' => $partner->email,
                'bank_details' => $partner->bank_details,
            ]);

            if ($response->successful()) {
                $sapId = $response->json('sap_id') ?? $response->json('vendor_number');
                return [
                    'status' => 'success',
                    'sap_id' => $sapId,
                    'message' => 'Partner synced to SAP.',
                ];
            }

            Log::warning('SAP partner sync failed', [
                'partner_id' => $partner->id,
                'status' => $response->status(),
            ]);

            return ['status' => 'error', 'message' => 'SAP returned status ' . $response->status()];
        } catch (\Throwable $e) {
            Log::error('SAP partner sync exception', ['error' => $e->getMessage()]);
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public function createDocument(Task $task): array
    {
        if (! $this->isEnabled()) {
            return ['status' => 'disabled', 'message' => 'SAP sync is not enabled.'];
        }

        try {
            $response = $this->request()->post('/documents', [
                'document_number' => $task->registration_number,
                'vendor_id' => $task->partner?->bin_iin,
                'category' => $task->category?->name,
                'amount' => $task->amount,
                'status' => $task->status->value,
                'created_at' => $task->created_at->toIso8601String(),
            ]);

            if ($response->successful()) {
                return [
                    'status' => 'success',
                    'sap_document_id' => $response->json('document_id'),
                    'message' => 'Document created in SAP.',
                ];
            }

            return ['status' => 'error', 'message' => 'SAP returned status ' . $response->status()];
        } catch (\Throwable $e) {
            Log::error('SAP document creation exception', ['error' => $e->getMessage()]);
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    private function request(): \Illuminate\Http\Client\PendingRequest
    {
        $apiUrl = Setting::get('sap_api_url', '');
        $apiKey = Setting::get('sap_api_key', '');

        return Http::baseUrl(rtrim($apiUrl, '/'))
            ->timeout(30)
            ->withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ]);
    }
}
