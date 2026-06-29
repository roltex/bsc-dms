<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\GoogleDriveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SettingController extends Controller
{
    public function googleStatus(): JsonResponse
    {
        $enabled = Setting::get('google_drive_enabled', false);
        $clientId = Setting::get('google_client_id', '');
        $clientSecret = Setting::get('google_client_secret', '');
        $refreshToken = Setting::get('google_refresh_token', '');

        $configured = $enabled && ! empty($clientId) && ! empty($clientSecret) && ! empty($refreshToken);

        return response()->json([
            'enabled' => (bool) $enabled,
            'configured' => $configured,
        ]);
    }

    public function getGoogleSettings(): JsonResponse
    {
        return response()->json([
            'google_drive_enabled' => (bool) Setting::get('google_drive_enabled', false),
            'google_client_id' => Setting::get('google_client_id', ''),
            'google_client_secret' => Setting::get('google_client_secret', ''),
            'google_authorized' => ! empty(Setting::get('google_refresh_token', '')),
        ]);
    }

    public function saveGoogleSettings(Request $request): JsonResponse
    {
        $request->validate([
            'google_drive_enabled' => 'required|boolean',
            'google_client_id' => 'nullable|string',
            'google_client_secret' => 'nullable|string',
        ]);

        $enabled = $request->boolean('google_drive_enabled');
        $clientId = $request->input('google_client_id', '');
        $clientSecret = $request->input('google_client_secret', '');

        $this->upsertSetting('google_drive_enabled', $enabled ? '1' : '0', 'boolean');
        $this->upsertSetting('google_client_id', $clientId, 'string');
        $this->upsertSetting('google_client_secret', $clientSecret, 'string');

        Cache::forget('google_drive_access_token');

        return response()->json(['message' => 'Google settings saved.']);
    }

    public function googleAuthUrl(): JsonResponse
    {
        $service = new GoogleDriveService();

        return response()->json([
            'url' => $service->getAuthUrl(),
        ]);
    }

    public function googleCallback(Request $request)
    {
        $code = $request->query('code');
        if (! $code) {
            return redirect(config('app.frontend_url', config('app.url')) . '/settings?google_auth=error&message=no_code');
        }

        try {
            $service = new GoogleDriveService();
            $service->handleCallback($code);

            return redirect(config('app.frontend_url', config('app.url')) . '/settings?google_auth=success');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Google OAuth callback error', ['error' => $e->getMessage()]);

            return redirect(config('app.frontend_url', config('app.url')) . '/settings?google_auth=error&message=' . urlencode($e->getMessage()));
        }
    }

    public function googleDisconnect(): JsonResponse
    {
        $this->upsertSetting('google_refresh_token', '', 'string');
        Cache::forget('google_drive_access_token');

        return response()->json(['message' => 'Google account disconnected.']);
    }

    private function upsertSetting(string $key, string $value, string $type): void
    {
        $setting = Setting::where('key', $key)->first();
        if ($setting) {
            $setting->update(['value' => $value]);
        } else {
            Setting::create([
                'group' => 'integrations',
                'key' => $key,
                'value' => $value,
                'type' => $type,
                'label' => ucwords(str_replace('_', ' ', $key)),
            ]);
        }
    }
}
