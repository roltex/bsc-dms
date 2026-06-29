<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AdataService
{
    private string $baseUrl;
    private string $tokenAuth;

    public function __construct()
    {
        $this->baseUrl = rtrim(Setting::get('adata_api_url', 'https://api.adata.kz/api'), '/');
        $this->tokenAuth = Setting::get('adata_api_key', '');
    }

    public function isConfigured(): bool
    {
        return ! empty($this->tokenAuth);
    }

    /**
     * Step 1: Initiate a check — returns a polling token.
     */
    public function initiate(string $bin): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'message' => 'ADATA API token not configured.'];
        }

        try {
            $url = "{$this->baseUrl}/company/info/{$this->tokenAuth}";

            $response = Http::timeout(15)
                ->acceptJson()
                ->get($url, ['iinBin' => $bin]);

            if (! $response->successful()) {
                Log::warning('ADATA initiate failed', ['status' => $response->status(), 'body' => $response->body()]);

                return ['success' => false, 'message' => 'ADATA API error (HTTP '.$response->status().')'];
            }

            $data = $response->json();

            if (! ($data['success'] ?? false)) {
                $msg = is_array($data['message'] ?? null)
                    ? collect($data['message'])->flatten()->implode('; ')
                    : ($data['message'] ?? 'Unknown error');

                return ['success' => false, 'message' => $msg];
            }

            $token = $data['token'] ?? $data['data']['token'] ?? null;

            if (! $token) {
                return ['success' => false, 'message' => 'No check token received from ADATA.'];
            }

            return ['success' => true, 'token' => $token];
        } catch (\Throwable $e) {
            Log::error('ADATA initiate error', ['bin' => $bin, 'error' => $e->getMessage()]);

            return ['success' => false, 'message' => 'Connection error: '.$e->getMessage()];
        }
    }

    /**
     * Step 2: Check result — returns data if ready, or status "wait".
     */
    public function check(string $token): array
    {
        if (! $this->isConfigured()) {
            return ['status' => 'error', 'message' => 'ADATA API token not configured.'];
        }

        try {
            $url = "{$this->baseUrl}/company/info/check/{$this->tokenAuth}";

            $response = Http::timeout(15)
                ->acceptJson()
                ->get($url, ['token' => $token]);

            if (! $response->successful()) {
                return ['status' => 'error', 'message' => 'ADATA check failed (HTTP '.$response->status().')'];
            }

            $data = $response->json();
            $message = $data['message'] ?? '';

            if ($message === 'ready') {
                $basic = $data['data']['basic'] ?? $data['data'] ?? [];

                return ['status' => 'ready', 'data' => $this->mapResponse($basic)];
            }

            return ['status' => 'wait', 'message' => $message ?: 'Data is still being prepared...'];
        } catch (\Throwable $e) {
            Log::error('ADATA check error', ['error' => $e->getMessage()]);

            return ['status' => 'error', 'message' => 'Connection error: '.$e->getMessage()];
        }
    }

    /**
     * Legacy single-call method (kept for backward compatibility).
     */
    public function checkReliability(string $bin): array
    {
        $init = $this->initiate($bin);
        if (! $init['success']) {
            return $this->errorResponse($bin, $init['message']);
        }

        for ($i = 0; $i < 15; $i++) {
            sleep(2);
            $result = $this->check($init['token']);

            if ($result['status'] === 'ready') {
                return array_merge(['status' => 'live', 'bin_iin' => $bin], $result['data']);
            }

            if ($result['status'] === 'error') {
                return $this->errorResponse($bin, $result['message']);
            }
        }

        return $this->errorResponse($bin, 'ADATA check timed out.');
    }

    private function mapResponse(array $data): array
    {
        return [
            'company_name' => $data['name_ru'] ?? $data['name_kgd'] ?? '',
            'short_name' => $data['short_name'] ?? '',
            'registration_date' => $data['date_registration'] ?? '',
            'legal_address' => $data['legal_address'] ?? '',
            'director' => $data['fullname_director'] ?? '',
            'director_iin' => $data['director_iin'] ?? '',
            'director_start_date' => $data['director_start_date'] ?? '',
            'employee_count' => $data['employee_count'] ?? null,
            'oked' => $data['oked'] ?? '',
            'oked_id' => $data['oked_id'] ?? '',
            'legal_form' => $data['legal_form'] ?? '',
            'type_of_ownership' => $data['type_of_ownership'] ?? '',
            'krp' => $data['krp'] ?? '',
            'kato_name' => $data['kato_name'] ?? '',

            'is_active' => ! (isset($data['end_date']) && $data['end_date'] && strtotime($data['end_date']) < time()),
            'is_nds_payer' => $data['is_nds_payer'] ?? false,
            'is_non_resident' => $data['is_non_resident'] ?? false,
            'non_resident_country' => $data['non_resident_country'] ?? null,

            'company_problems' => (bool) ($data['company_problems'] ?? false),
            'financial_problems' => (bool) ($data['financial_problems'] ?? false),
            'unreliable_zakup' => (bool) ($data['unreliable_zakup'] ?? false),
            'head_problems' => (bool) ($data['head_problems'] ?? false),

            'reliability_score' => $this->calculateScore($data),
            'source_link' => $data['source_link'] ?? null,
        ];
    }

    private function calculateScore(array $data): int
    {
        $score = 100;

        if ($data['company_problems'] ?? false) {
            $score -= 30;
        }
        if ($data['financial_problems'] ?? false) {
            $score -= 25;
        }
        if ($data['unreliable_zakup'] ?? false) {
            $score -= 20;
        }
        if ($data['head_problems'] ?? false) {
            $score -= 15;
        }
        if ($data['is_non_resident'] ?? false) {
            $score -= 5;
        }

        $endDate = $data['end_date'] ?? null;
        if ($endDate && strtotime($endDate) < time()) {
            $score -= 30;
        }

        return max(0, $score);
    }

    private function errorResponse(string $bin, string $message): array
    {
        return [
            'status' => 'error',
            'bin_iin' => $bin,
            'company_name' => '',
            'reliability_score' => null,
            'message' => $message,
        ];
    }
}
