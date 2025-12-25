<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
    }

    public function test_health_endpoint_reports_available_services(): void
    {
        $redis = \Mockery::mock();
        $redis->shouldReceive('ping')->once()->andReturn('PONG');
        Redis::shouldReceive('connection')->once()->andReturn($redis);

        Http::fake([
            '*' => Http::response([
                'code' => 'Ok',
                'routes' => [],
            ], 200),
        ]);

        $response = $this->getJson('/api/health');

        $response->assertOk()
            ->assertJson([
                'status' => 'ok',
                'db' => true,
                'redis' => true,
                'osrm' => true,
                'osrm_message' => 'ok',
            ]);

        Http::assertSent(fn ($request) => str_contains(
            $request->url(),
            '/route/v1/driving/-74.0060,40.7128;-73.935242,40.73061'
        ));
    }

    public function test_health_endpoint_handles_osrm_failure_gracefully(): void
    {
        $redis = \Mockery::mock();
        $redis->shouldReceive('ping')->once()->andReturn('PONG');
        Redis::shouldReceive('connection')->once()->andReturn($redis);

        Http::fake([
            '*' => Http::response(['code' => 'Error'], 500),
        ]);

        $response = $this->getJson('/api/health');

        $response->assertOk()
            ->assertJson([
                'status' => 'ok',
                'db' => true,
                'redis' => true,
                'osrm' => false,
            ]);

        $this->assertStringContainsString('OSRM', $response->json('osrm_message'));
    }

    public function test_health_endpoint_indicates_missing_osrm_configuration(): void
    {
        config(['services.osrm.base_url' => '']);

        $response = $this->getJson('/api/health');

        $response->assertOk()
            ->assertJson([
                'status' => 'ok',
                'osrm' => false,
            ]);

        $this->assertStringContainsString('not configured', $response->json('osrm_message'));
    }
}
