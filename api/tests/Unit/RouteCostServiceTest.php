<?php

namespace Tests\Unit;

use App\Services\OsrmClient;
use App\Services\Routing\RouteCostService;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Mockery;
use Tests\TestCase;

class RouteCostServiceTest extends TestCase
{
    public function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_build_matrix_uses_cache_and_normalizes_output(): void
    {
        $stops = [
            ['id' => 'a', 'lat' => '52.5', 'lng' => 13.4],
            ['id' => 'b', 'lat' => 52.6, 'lng' => '13.5'],
        ];

        $expectedKey = 'osrm:table:' . hash('sha256', '13.4,52.5;13.5,52.6');

        $osrmResponse = [
            'code' => 'Ok',
            'durations' => [
                [0, 10.5],
                [10.6, 0],
            ],
            'distances' => [
                [0, 1200],
                [1190, 0],
            ],
        ];

        $client = Mockery::mock(OsrmClient::class);
        $client->shouldReceive('table')
            ->once()
            ->with([
                ['lat' => 52.5, 'lng' => 13.4],
                ['lat' => 52.6, 'lng' => 13.5],
            ], [])
            ->andReturn($osrmResponse);

        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(function ($key, $ttl, $callback) use ($expectedKey) {
                $this->assertSame($expectedKey, $key);
                $this->assertSame(6 * 60 * 60, $ttl);

                $result = $callback();

                $this->assertSame('osrm', $result['metadata']['provider']);
                $this->assertSame('driving', $result['metadata']['profile']);
                $this->assertSame(2, $result['metadata']['stop_count']);
                $this->assertNotEmpty($result['metadata']['generated_at']);

                $this->assertSame([
                    ['id' => 'a', 'lat' => 52.5, 'lng' => 13.4],
                    ['id' => 'b', 'lat' => 52.6, 'lng' => 13.5],
                ], $result['stops']);

                $this->assertCount(2, $result['durations_s']);
                $this->assertCount(2, $result['distances_m']);

                return $result;
            });

        $service = new RouteCostService($client);

        $result = $service->buildMatrix($stops);

        $this->assertArrayHasKey('durations_s', $result);
        $this->assertArrayHasKey('distances_m', $result);
    }

    public function test_build_matrix_validates_square_matrix(): void
    {
        $stops = [
            ['id' => 1, 'lat' => 1, 'lng' => 2],
            ['id' => 2, 'lat' => 3, 'lng' => 4],
        ];

        $client = Mockery::mock(OsrmClient::class);
        $client->shouldReceive('table')->andReturn([
            'code' => 'Ok',
            'durations' => [[0, 1]],
            'distances' => [[0, 2]],
        ]);

        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(function ($key, $ttl, $callback) {
                return $callback();
            });

        $service = new RouteCostService($client);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('durations matrix row 0 must contain 2 entries.');

        $service->buildMatrix($stops);
    }
}
