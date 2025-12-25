<?php

namespace Tests\Feature;

use App\Services\Routing\RouteCostService;
use Mockery;
use Tests\TestCase;

class RoutingMatrixCommandTest extends TestCase
{
    public function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_fails_when_csv_missing(): void
    {
        $this->artisan('routing:matrix missing.csv')
            ->expectsOutputToContain('CSV file not found')
            ->assertExitCode(1);
    }

    public function test_fails_when_required_column_missing(): void
    {
        $csvPath = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($csvPath, "id,lat\n1,2\n");

        $this->artisan("routing:matrix {$csvPath}")
            ->expectsOutputToContain('CSV is missing required column: lng')
            ->assertExitCode(1);

        @unlink($csvPath);
    }

    public function test_generates_matrix_and_writes_to_file(): void
    {
        $csvPath = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($csvPath, "identifier,lat,lng\nA,1.1,2.2\nB,3.3,4.4\n");

        $outPath = tempnam(sys_get_temp_dir(), 'matrix');

        $mock = Mockery::mock(RouteCostService::class);
        $mock->expects('buildMatrix')->once()->with([
            ['id' => 'A', 'lat' => '1.1', 'lng' => '2.2'],
            ['id' => 'B', 'lat' => '3.3', 'lng' => '4.4'],
        ], [])->andReturn([
            'metadata' => ['provider' => 'osrm'],
            'stops' => [],
            'durations_s' => [[0, 1], [1, 0]],
            'distances_m' => [[0, 100], [100, 0]],
        ]);

        $this->app->instance(RouteCostService::class, $mock);

        $this->artisan("routing:matrix {$csvPath} --out={$outPath} --id-column=identifier")
            ->expectsOutputToContain('Matrix written to')
            ->assertExitCode(0);

        $output = file_get_contents($outPath);

        $this->assertIsString($output);
        $this->assertStringContainsString('"provider"', $output);
        $this->assertStringContainsString('"durations_s"', $output);

        @unlink($csvPath);
        @unlink($outPath);
    }
}
