<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OsrmRouteTest extends TestCase
{
    public function test_route_endpoint_returns_ok_when_osrm_available(): void
    {
        $osrmUrl = config('services.osrm.url');

        if (empty($osrmUrl)) {
            $this->markTestSkipped('OSRM URL not configured.');
        }

        $from = '13.388860,52.517037';
        $to = '13.397634,52.529407';
        $routeUrl = rtrim($osrmUrl, '/') . '/route/v1/driving/' . $from . ';' . $to;

        try {
            $directResponse = Http::timeout(5)->get($routeUrl, [
                'overview' => 'false',
                'steps' => 'false',
            ]);
        } catch (\Exception $exception) {
            $this->markTestSkipped('OSRM not reachable: ' . $exception->getMessage());
        }

        if (! $directResponse->successful()) {
            $this->markTestSkipped('OSRM not reachable (HTTP ' . $directResponse->status() . ').');
        }

        $directData = $directResponse->json();

        if (! is_array($directData) || ($directData['code'] ?? null) !== 'Ok') {
            $this->markTestSkipped('OSRM did not return Ok for the sample coordinates.');
        }

        $response = $this->getJson('/api/osrm/route?from=' . urlencode($from) . '&to=' . urlencode($to));

        $response->assertOk()
            ->assertJsonPath('osrm.code', 'Ok')
            ->assertJsonStructure([
                'distance_meters',
                'duration_seconds',
                'osrm' => [
                    'routes',
                    'code',
                ],
            ]);
    }
}
