<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OsrmTableTest extends TestCase
{
    public function test_table_endpoint_requires_coords(): void
    {
        $this->getJson('/api/v1/osrm/table')
            ->assertStatus(422)
            ->assertJsonStructure(['error']);
    }

    public function test_table_endpoint_validates_coordinate_format(): void
    {
        $this->getJson('/api/v1/osrm/table?coords=52.5,|abc')
            ->assertStatus(422)
            ->assertJsonStructure(['error']);
    }

    public function test_table_endpoint_returns_matrix_when_osrm_available(): void
    {
        $osrmUrl = config('services.osrm.base_url');

        if (empty($osrmUrl)) {
            $this->markTestSkipped('OSRM URL not configured.');
        }

        $coordinates = [
            '52.517037,13.388860',
            '52.529407,13.397634',
        ];

        $directRoute = implode(';', [
            '13.388860,52.517037',
            '13.397634,52.529407',
        ]);

        $tableUrl = rtrim($osrmUrl, '/') . '/table/v1/driving/' . $directRoute;

        try {
            $directResponse = Http::timeout(5)->get($tableUrl, [
                'annotations' => 'duration,distance',
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

        $response = $this->getJson('/api/v1/osrm/table?coords=' . implode('|', $coordinates));

        $response->assertOk()
            ->assertJsonPath('code', 'Ok')
            ->assertJsonStructure([
                'code',
                'sources',
                'destinations',
                'durations',
                'distances',
            ]);

        $responseData = $response->json();

        $this->assertIsArray($responseData['durations']);
        $this->assertIsArray($responseData['distances']);
        $this->assertCount(2, $responseData['durations']);
        $this->assertCount(2, $responseData['distances']);
        $this->assertCount(2, $responseData['durations'][0]);
        $this->assertCount(2, $responseData['distances'][0]);
    }
}
