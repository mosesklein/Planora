<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use App\Services\Routing\RouteCostService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('routing:matrix {csv} {--out=} {--id-column=id} {--lat-column=lat} {--lng-column=lng}', function (RouteCostService $routeCostService) {
    $csvPath = $this->argument('csv');

    if (! is_readable($csvPath)) {
        $this->error("CSV file not found: {$csvPath}");

        return 1;
    }

    $handle = fopen($csvPath, 'r');

    if ($handle === false) {
        $this->error("Unable to open CSV file: {$csvPath}");

        return 1;
    }

    $headers = fgetcsv($handle);

    if ($headers === false) {
        fclose($handle);
        $this->error('CSV file is empty or invalid.');

        return 1;
    }

    $idColumn = $this->option('id-column');
    $latColumn = $this->option('lat-column');
    $lngColumn = $this->option('lng-column');

    $headerIndexes = array_flip($headers);
    foreach ([$idColumn, $latColumn, $lngColumn] as $column) {
        if (! array_key_exists($column, $headerIndexes)) {
            fclose($handle);
            $this->error("CSV is missing required column: {$column}");

            return 1;
        }
    }

    $stops = [];

    while (($row = fgetcsv($handle)) !== false) {
        if ($row === [null] || $row === false) {
            continue;
        }

        if (count(array_filter($row, fn ($value) => $value !== null && $value !== '')) === 0) {
            continue;
        }

        $stops[] = [
            'id' => $row[$headerIndexes[$idColumn]] ?? null,
            'lat' => $row[$headerIndexes[$latColumn]] ?? null,
            'lng' => $row[$headerIndexes[$lngColumn]] ?? null,
        ];
    }

    fclose($handle);

    try {
        $matrix = $routeCostService->buildMatrix($stops);
    } catch (\Throwable $e) {
        $this->error($e->getMessage());

        return 1;
    }

    $json = json_encode($matrix, JSON_PRETTY_PRINT);

    if ($json === false) {
        $this->error('Failed to encode matrix as JSON.');

        return 1;
    }

    $outPath = $this->option('out');

    if ($outPath) {
        if (file_put_contents($outPath, $json) === false) {
            $this->error("Unable to write matrix to {$outPath}");

            return 1;
        }

        $this->info("Matrix written to {$outPath}");
    } else {
        $this->line($json);
    }

    return 0;
})->purpose('Generate an OSRM distance matrix from a CSV file');

Artisan::command('routing:diagnose', function () {
    $this->info('Running routing diagnostics...');

    $dbHealthy = false;
    $redisHealthy = false;
    $osrmHealthy = false;
    $baseUrl = rtrim((string) config('services.osrm.base_url'), '/');

    $this->comment('Checking database connectivity...');
    try {
        DB::connection()->getPdo();
        $dbHealthy = true;
        $this->info('Database connection: ok');
    } catch (\Throwable $e) {
        $this->error('Database connection failed: ' . $e->getMessage());
    }

    $this->comment('Checking Redis connectivity...');
    try {
        Redis::connection()->ping();
        $redisHealthy = true;
        $this->info('Redis connection: ok');
    } catch (\Throwable $e) {
        $this->error('Redis connection failed: ' . $e->getMessage());
    }

    $this->comment('Checking OSRM configuration...');
    if ($baseUrl === '') {
        $this->error('OSRM base URL is not configured. Set OSRM_URL=http://osrm:5000 when using Docker.');
    } elseif (! filter_var($baseUrl, FILTER_VALIDATE_URL)) {
        $this->error('OSRM base URL is not a valid URL. Set OSRM_URL=http://osrm:5000 in your .env.');
    } else {
        $host = parse_url($baseUrl, PHP_URL_HOST);

        if ($host === null) {
            $this->error('OSRM base URL is missing a host.');
        } else {
            $resolvedHost = gethostbyname($host);
            $isIp = filter_var($host, FILTER_VALIDATE_IP) !== false;

            if (! $isIp && $resolvedHost === $host) {
                $this->error("OSRM host '{$host}' could not be resolved. Is the osrm service running and on the Sail network?");
            } else {
                $this->info("OSRM host resolved to {$resolvedHost}.");

                try {
                    $response = Http::timeout(5)->get(
                        $baseUrl . '/route/v1/driving/-74.0060,40.7128;-73.935242,40.73061',
                        [
                            'overview' => 'false',
                            'steps' => 'false',
                        ]
                    );

                    if (! $response->successful()) {
                        $this->error('OSRM responded with HTTP ' . $response->status() . '.');
                    } else {
                        $body = $response->json();
                        $osrmHealthy = is_array($body) && ($body['code'] ?? null) === 'Ok';

                        if ($osrmHealthy) {
                            $this->info('OSRM routing response: ok');
                        } else {
                            $this->error('OSRM returned a non-Ok response code.');
                        }
                    }
                } catch (\Throwable $e) {
                    $this->error('OSRM request failed: ' . $e->getMessage());
                }
            }
        }
    }

    $allHealthy = $dbHealthy && $redisHealthy && $osrmHealthy;

    if (! $allHealthy) {
        $this->error('Routing diagnostics failed. Resolve the errors above and retry.');

        return 1;
    }

    $this->info('All routing dependencies are healthy.');

    return 0;
})->purpose('Diagnose database, Redis, and OSRM connectivity');
