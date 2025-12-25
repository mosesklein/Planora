<?php

namespace App\Http\Controllers;

use App\Models\RoutingJob;
use App\Services\Routing\RouteCostService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

class RoutingJobController extends Controller
{
    public function index()
    {
        $jobs = RoutingJob::orderBy('created_at', 'desc')
            ->limit(25)
            ->get();

        return response()->json($jobs);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:csv', 'max:20480'],
        ]);

        $file = $validated['file'];
        $storedPath = $file->store('routing-jobs');

        $routingJob = RoutingJob::create([
            'id' => (string) Str::uuid(),
            'status' => 'uploaded',
            'original_filename' => $file->getClientOriginalName(),
            'stored_path' => $storedPath,
        ]);

        return response()->json([
            'id' => $routingJob->id,
            'status' => $routingJob->status,
        ], 201);
    }

    public function show(string $id)
    {
        $job = RoutingJob::findOrFail($id);

        return response()->json($job);
    }

    public function process(string $id, RouteCostService $routeCostService)
    {
        if (! app()->environment(['local', 'testing'])) {
            abort(403, 'Routing job processing is only available locally.');
        }

        $job = RoutingJob::findOrFail($id);

        $job->status = 'processing';
        $job->error_message = null;
        $job->save();

        try {
            $stops = $this->parseStopsFromCsv($job->stored_path);

            $matrix = $routeCostService->buildMatrix($stops);

            [$jsonPath, $csvPath] = $this->storeOutputs($job->id, $matrix);

            $job->status = 'succeeded';
            $job->output_json_path = $jsonPath;
            $job->output_csv_path = $csvPath;
            $job->save();

            return response()->json([
                'id' => $job->id,
                'status' => $job->status,
                'output_json_path' => $jsonPath,
                'output_csv_path' => $csvPath,
            ]);
        } catch (InvalidArgumentException $exception) {
            return $this->failJob($job, $exception->getMessage(), 422);
        } catch (Throwable $exception) {
            return $this->failJob($job, $exception->getMessage(), 500);
        }
    }

    /**
     * @return array<int, array{id: string|int, lat: float|int|string, lng: float|int|string}>
     */
    private function parseStopsFromCsv(string $storedPath): array
    {
        if (! Storage::disk('local')->exists($storedPath)) {
            throw new InvalidArgumentException("CSV file not found at {$storedPath}.");
        }

        $stream = Storage::disk('local')->readStream($storedPath);

        if (! $stream) {
            throw new InvalidArgumentException("Unable to read CSV file at {$storedPath}.");
        }

        $headers = fgetcsv($stream);

        if ($headers === false) {
            fclose($stream);
            throw new InvalidArgumentException('CSV file is empty or invalid.');
        }

        $requiredColumns = ['id', 'lat', 'lng'];
        $headerIndexes = array_flip($headers);

        foreach ($requiredColumns as $column) {
            if (! array_key_exists($column, $headerIndexes)) {
                fclose($stream);
                throw new InvalidArgumentException("CSV is missing required column: {$column}");
            }
        }

        $stops = [];

        while (($row = fgetcsv($stream)) !== false) {
            if ($row === [null] || $row === false) {
                continue;
            }

            if (count(array_filter($row, fn ($value) => $value !== null && $value !== '')) === 0) {
                continue;
            }

            $stops[] = [
                'id' => $row[$headerIndexes['id']] ?? null,
                'lat' => $row[$headerIndexes['lat']] ?? null,
                'lng' => $row[$headerIndexes['lng']] ?? null,
            ];
        }

        fclose($stream);

        return $stops;
    }

    private function storeOutputs(string $jobId, array $matrix): array
    {
        $baseDir = "routing_outputs/{$jobId}";
        Storage::disk('local')->makeDirectory($baseDir);

        $jsonPath = $baseDir . '/matrix.json';
        Storage::disk('local')->put($jsonPath, json_encode($matrix, JSON_PRETTY_PRINT));

        $csvPath = $baseDir . '/durations.csv';
        $this->writeMatrixCsv($csvPath, $matrix);

        return [$jsonPath, $csvPath];
    }

    private function writeMatrixCsv(string $path, array $matrix): void
    {
        $stops = $matrix['stops'] ?? [];
        $durations = $matrix['durations_s'] ?? [];

        $handle = fopen('php://temp', 'w+');

        $headers = ['id'];
        foreach ($stops as $stop) {
            $headers[] = (string) ($stop['id'] ?? '');
        }
        fputcsv($handle, $headers);

        foreach ($durations as $index => $row) {
            $id = $stops[$index]['id'] ?? $index;
            fputcsv($handle, array_merge([(string) $id], $row));
        }

        rewind($handle);
        Storage::disk('local')->put($path, stream_get_contents($handle) ?: '');
        fclose($handle);
    }

    private function failJob(RoutingJob $job, string $message, int $statusCode)
    {
        $job->status = 'failed';
        $job->error_message = $message;
        $job->save();

        return response()->json([
            'id' => $job->id,
            'status' => $job->status,
            'error_message' => $message,
        ], $statusCode);
    }
}
