<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleDriveService
{
    public function isConfigured(): bool
    {
        $enabled = Setting::get('google_drive_enabled', false);
        if (! $enabled) {
            return false;
        }

        $refreshToken = Setting::get('google_refresh_token', '');
        $clientId = Setting::get('google_client_id', '');
        $clientSecret = Setting::get('google_client_secret', '');

        return ! empty($refreshToken) && ! empty($clientId) && ! empty($clientSecret);
    }

    /**
     * Build the Google OAuth2 authorization URL.
     */
    public function getAuthUrl(): string
    {
        $clientId = Setting::get('google_client_id', '');
        $redirectUri = $this->getRedirectUri();

        $params = http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/drive.file',
            'access_type' => 'offline',
            'prompt' => 'consent',
        ]);

        return "https://accounts.google.com/o/oauth2/v2/auth?{$params}";
    }

    /**
     * Exchange an authorization code for access + refresh tokens.
     */
    public function handleCallback(string $code): void
    {
        $clientId = Setting::get('google_client_id', '');
        $clientSecret = Setting::get('google_client_secret', '');
        $redirectUri = $this->getRedirectUri();

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'code' => $code,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code',
        ]);

        if (! $response->successful()) {
            Log::error('Google OAuth callback failed', ['body' => $response->body()]);
            throw new \RuntimeException('Failed to exchange authorization code: ' . $response->body());
        }

        $data = $response->json();

        if (! empty($data['refresh_token'])) {
            Setting::set('google_refresh_token', $data['refresh_token']);
        }

        if (! empty($data['access_token'])) {
            Cache::put('google_drive_access_token', $data['access_token'], ($data['expires_in'] ?? 3600) - 60);
        }
    }

    public function uploadDocx(string $filePath, string $fileName): array
    {
        $token = $this->getAccessToken();

        $metadata = json_encode([
            'name' => $fileName . '.docx',
            'mimeType' => 'application/vnd.google-apps.document',
        ]);

        $fileContent = file_get_contents($filePath);
        $boundary = 'efes_upload_' . uniqid();

        $body = "--{$boundary}\r\n"
            . "Content-Type: application/json; charset=UTF-8\r\n\r\n"
            . $metadata . "\r\n"
            . "--{$boundary}\r\n"
            . "Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document\r\n\r\n"
            . $fileContent . "\r\n"
            . "--{$boundary}--";

        $response = Http::withToken($token)
            ->withHeaders(['Content-Type' => "multipart/related; boundary={$boundary}"])
            ->withBody($body, "multipart/related; boundary={$boundary}")
            ->post('https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart&fields=id,webViewLink');

        if (! $response->successful()) {
            Log::error('Google Drive upload failed', ['status' => $response->status(), 'body' => $response->body()]);
            throw new \RuntimeException('Failed to upload file to Google Drive: ' . $response->body());
        }

        $fileId = $response->json('id');

        Http::withToken($token)
            ->post("https://www.googleapis.com/drive/v3/files/{$fileId}/permissions", [
                'role' => 'writer',
                'type' => 'anyone',
            ]);

        return [
            'fileId' => $fileId,
            'editUrl' => "https://docs.google.com/document/d/{$fileId}/edit",
        ];
    }

    public function downloadDocx(string $fileId, string $savePath): void
    {
        $token = $this->getAccessToken();

        $response = Http::withToken($token)
            ->timeout(60)
            ->get("https://www.googleapis.com/drive/v3/files/{$fileId}/export", [
                'mimeType' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ]);

        if (! $response->successful()) {
            Log::error('Google Drive download failed', ['fileId' => $fileId, 'status' => $response->status()]);
            throw new \RuntimeException('Failed to download file from Google Drive.');
        }

        $dir = dirname($savePath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($savePath, $response->body());
    }

    public function deleteFile(string $fileId): void
    {
        try {
            $token = $this->getAccessToken();
            Http::withToken($token)->delete("https://www.googleapis.com/drive/v3/files/{$fileId}");
        } catch (\Throwable $e) {
            Log::warning('Google Drive delete failed', ['fileId' => $fileId, 'error' => $e->getMessage()]);
        }
    }

    private function getAccessToken(): string
    {
        $cached = Cache::get('google_drive_access_token');
        if ($cached) {
            return $cached;
        }

        $clientId = Setting::get('google_client_id', '');
        $clientSecret = Setting::get('google_client_secret', '');
        $refreshToken = Setting::get('google_refresh_token', '');

        if (empty($refreshToken) || empty($clientId) || empty($clientSecret)) {
            throw new \RuntimeException('Google Drive OAuth2 credentials are not configured.');
        }

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
        ]);

        if (! $response->successful() || ! $response->json('access_token')) {
            Log::error('Google OAuth refresh failed', ['body' => $response->body()]);
            throw new \RuntimeException('Failed to refresh Google API access token. Please re-authorize in Settings.');
        }

        $token = $response->json('access_token');
        $expiresIn = $response->json('expires_in', 3600);
        Cache::put('google_drive_access_token', $token, $expiresIn - 60);

        return $token;
    }

    private function getRedirectUri(): string
    {
        return rtrim(config('app.url'), '/') . '/api/google/callback';
    }
}
