<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Throwable;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $dbOk = $this->checkDatabase();
        $redisOk = $this->checkRedis();
        [$osrmOk, $osrmMessage] = $this->checkOsrm();

        $isHealthy = $dbOk && $redisOk && $osrmOk;

        return response()->json([
            'status' => $isHealthy ? 'ok' : 'unhealthy',
            'db' => $dbOk,
            'redis' => $redisOk,
            'osrm' => $osrmOk,
            'osrm_message' => $osrmMessage,
        ], $isHealthy ? 200 : 503);
    }

    private function checkDatabase(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function checkRedis(): bool
    {
        try {
            Redis::connection()->ping();

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function checkOsrm(): array
    {
        $baseUrl = rtrim((string) config('services.osrm.base_url'), '/');

        if ($baseUrl === '') {
            return [false, 'OSRM_URL is not configured. Set OSRM_URL=http://osrm:5000 when using Docker.'];
        }

        if (! filter_var($baseUrl, FILTER_VALIDATE_URL)) {
            return [false, 'OSRM_URL is not a valid URL.'];
        }

        $coordinates = '-74.0060,40.7128;-73.935242,40.73061';

        try {
            $response = Http::timeout(5)->get(
                $baseUrl . '/route/v1/driving/' . $coordinates,
                [
                    'overview' => 'false',
                    'steps' => 'false',
                ]
            );
        } catch (Throwable $e) {
            return [false, 'Unable to reach OSRM at ' . $baseUrl . ': ' . $e->getMessage()];
        }

        if (! $response->successful()) {
            return [false, 'OSRM responded with HTTP ' . $response->status() . '.'];
        }

        $data = $response->json();

        $ok = is_array($data) && ($data['code'] ?? null) === 'Ok';

        return [$ok, $ok ? 'ok' : 'OSRM returned a non-Ok response code.'];
    }
}
