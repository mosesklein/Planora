<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
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
