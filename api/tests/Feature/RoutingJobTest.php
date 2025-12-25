<?php

namespace Tests\Feature;

use App\Models\RoutingJob;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class RoutingJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_upload_csv_and_create_routing_job(): void
    {
        Storage::fake('local');

        $response = $this->post('/api/routing-jobs', [
            'file' => UploadedFile::fake()->create('routes.csv', 100, 'text/csv'),
        ], ['Accept' => 'application/json']);

        $response->assertCreated()
            ->assertJsonStructure([
                'id',
                'status',
            ]);

        $jobId = $response->json('id');

        $this->assertDatabaseHas('routing_jobs', [
            'id' => $jobId,
            'status' => 'uploaded',
            'original_filename' => 'routes.csv',
        ]);

        $storedPath = RoutingJob::find($jobId)->stored_path;
        Storage::disk('local')->assertExists($storedPath);
    }

    public function test_upload_requires_csv_and_size_limits(): void
    {
        $response = $this->post('/api/routing-jobs', [
            'file' => UploadedFile::fake()->create('document.txt', 10, 'text/plain'),
        ], ['Accept' => 'application/json']);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file']);

        $response = $this->post('/api/routing-jobs', [
            'file' => UploadedFile::fake()->create('too-large.csv', 25000, 'text/csv'),
        ], ['Accept' => 'application/json']);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }

    public function test_can_list_latest_routing_jobs(): void
    {
        $base = now();

        Carbon::setTestNow($base->copy()->subMinutes(3));
        $first = RoutingJob::create([
            'id' => (string) Str::uuid(),
            'status' => 'uploaded',
            'original_filename' => 'first.csv',
            'stored_path' => 'routing-jobs/first.csv',
        ]);

        Carbon::setTestNow($base->copy()->subMinutes(2));
        $second = RoutingJob::create([
            'id' => (string) Str::uuid(),
            'status' => 'processing',
            'original_filename' => 'second.csv',
            'stored_path' => 'routing-jobs/second.csv',
        ]);

        Carbon::setTestNow($base->copy()->subMinute());
        $third = RoutingJob::create([
            'id' => (string) Str::uuid(),
            'status' => 'succeeded',
            'original_filename' => 'third.csv',
            'stored_path' => 'routing-jobs/third.csv',
        ]);

        Carbon::setTestNow();

        $response = $this->getJson('/api/routing-jobs');

        $response->assertOk();

        $ids = array_column($response->json(), 'id');

        $this->assertEquals([$third->id, $second->id, $first->id], $ids);
    }

    public function test_can_show_routing_job_details(): void
    {
        $routingJob = RoutingJob::create([
            'id' => (string) Str::uuid(),
            'status' => 'failed',
            'original_filename' => 'problem.csv',
            'stored_path' => 'routing-jobs/problem.csv',
            'error_message' => 'Parsing error',
        ]);

        $response = $this->getJson('/api/routing-jobs/' . $routingJob->id);

        $response->assertOk()
            ->assertJson([
                'id' => $routingJob->id,
                'status' => 'failed',
                'original_filename' => 'problem.csv',
                'stored_path' => 'routing-jobs/problem.csv',
                'error_message' => 'Parsing error',
            ]);
    }

    public function test_can_process_job_and_persist_outputs(): void
    {
        Storage::fake('local');
        config()->set('services.osrm.base_url', 'http://osrm.test');

        $csvContent = "id,lat,lng\nA,40.0,-73.0\nB,41.0,-74.0\n";
        $storedPath = 'routing-jobs/to-process.csv';
        Storage::disk('local')->put($storedPath, $csvContent);

        $job = RoutingJob::create([
            'id' => (string) Str::uuid(),
            'status' => 'uploaded',
            'original_filename' => 'to-process.csv',
            'stored_path' => $storedPath,
        ]);

        Http::fake([
            'http://osrm.test/table/v1/driving/*' => Http::response([
                'code' => 'Ok',
                'durations' => [[0, 10], [10, 0]],
                'distances' => [[0, 1000], [1000, 0]],
            ], 200),
        ]);

        $response = $this->postJson('/api/routing-jobs/' . $job->id . '/process');

        $response->assertOk()
            ->assertJson([
                'id' => $job->id,
                'status' => 'succeeded',
            ])
            ->assertJsonStructure([
                'output_json_path',
                'output_csv_path',
            ]);

        $this->assertDatabaseHas('routing_jobs', [
            'id' => $job->id,
            'status' => 'succeeded',
        ]);

        $jsonPath = $response->json('output_json_path');
        $csvPath = $response->json('output_csv_path');

        Storage::disk('local')->assertExists($jsonPath);
        Storage::disk('local')->assertExists($csvPath);

        $matrix = json_decode(Storage::disk('local')->get($jsonPath), true);
        $this->assertEquals('osrm', $matrix['metadata']['provider']);
        $this->assertEquals([[0, 10], [10, 0]], $matrix['durations_s']);

        $csvContent = Storage::disk('local')->get($csvPath);
        $this->assertStringContainsString('A,B', $csvContent);
        $this->assertStringContainsString('A,0,10', $csvContent);
        $this->assertStringContainsString('B,10,0', $csvContent);
    }

    public function test_process_job_returns_validation_error_for_missing_columns(): void
    {
        Storage::fake('local');
        $storedPath = 'routing-jobs/invalid.csv';
        Storage::disk('local')->put($storedPath, "lat,lng\n40,-73\n");

        $job = RoutingJob::create([
            'id' => (string) Str::uuid(),
            'status' => 'uploaded',
            'original_filename' => 'invalid.csv',
            'stored_path' => $storedPath,
        ]);

        $response = $this->postJson('/api/routing-jobs/' . $job->id . '/process');

        $response->assertStatus(422)
            ->assertJson([
                'id' => $job->id,
                'status' => 'failed',
            ]);

        $this->assertDatabaseHas('routing_jobs', [
            'id' => $job->id,
            'status' => 'failed',
        ]);
    }
}
