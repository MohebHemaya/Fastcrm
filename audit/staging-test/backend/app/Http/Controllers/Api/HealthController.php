<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    /**
     * Check system health (API, DB, Time).
     */
    public function __invoke(): JsonResponse
    {
        $dbOk = false;
        try {
            DB::connection()->getPdo();
            $dbOk = true;
        } catch (\Throwable) {
            $dbOk = false;
        }

        $status = $dbOk ? 'ok' : 'fail';
        $statusCode = $dbOk ? 200 : 503;
        $isoTime = now()->toIso8601String();

        return response()->json([
            'status' => $status,
            'time' => $isoTime,
            'db' => $dbOk ? 'ok' : 'fail',
            'data' => [
                'status' => $status,
                'time' => $isoTime,
                'db' => $dbOk ? 'ok' : 'fail',
            ],
            'meta' => null,
            'message' => $dbOk ? __('api.health_ok') : __('api.db_failed'),
        ], $statusCode);
    }
}
