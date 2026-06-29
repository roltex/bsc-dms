<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'app' => true,
            'database' => false,
            'cache' => false,
        ];

        try {
            DB::connection()->getPdo();
            $checks['database'] = true;
        } catch (\Throwable) {
            $checks['database'] = false;
        }

        try {
            Cache::store()->put('health_check', 'ok', 10);
            $checks['cache'] = Cache::store()->get('health_check') === 'ok';
        } catch (\Throwable) {
            $checks['cache'] = false;
        }

        $healthy = $checks['app'] && $checks['database'];
        $status = $healthy ? 'ok' : 'degraded';

        return response()->json([
            'status' => $status,
            'checks' => $checks,
            'timestamp' => now()->toIso8601String(),
        ], $healthy ? 200 : 503);
    }
}
