<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ParagraphService
{
    public function search(string $query, int $limit = 20): array
    {
        $apiUrl = Setting::get('paragraph_api_url', '');
        $apiKey = Setting::get('paragraph_api_key', '');

        if (empty($apiUrl) || empty($apiKey)) {
            return [
                'status' => 'stub',
                'query' => $query,
                'results' => [],
                'message' => 'Paragraph API credentials not configured. Set them in Admin > System Settings.',
            ];
        }

        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Accept' => 'application/json',
                ])
                ->get(rtrim($apiUrl, '/') . '/search', [
                    'q' => $query,
                    'limit' => $limit,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'status' => 'live',
                    'query' => $query,
                    'results' => $data['results'] ?? $data['items'] ?? $data,
                    'total' => $data['total'] ?? count($data['results'] ?? $data['items'] ?? []),
                ];
            }

            return [
                'status' => 'error',
                'query' => $query,
                'results' => [],
                'message' => 'Paragraph API returned status ' . $response->status(),
            ];
        } catch (\Throwable $e) {
            Log::error('Paragraph API error', ['query' => $query, 'error' => $e->getMessage()]);
            return [
                'status' => 'error',
                'query' => $query,
                'results' => [],
                'message' => 'Connection error: ' . $e->getMessage(),
            ];
        }
    }
}
