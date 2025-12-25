<?php

namespace App\Services\Routing;

use App\Services\OsrmClient;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

class RouteCostService
{
    public function __construct(private OsrmClient $osrmClient)
    {
    }

    /**
     * @param array<int, array{id: string|int, lat: float|int|string, lng: float|int|string}> $stops
     * @param array<string, mixed> $opts
     */
    public function buildMatrix(array $stops, array $opts = []): array
    {
        $maxLocations = (int) config('services.osrm.table.max_locations', 100);

        if (count($stops) < 2) {
            throw new InvalidArgumentException('At least two stops are required to build a matrix.');
        }

        if (count($stops) > $maxLocations) {
            throw new InvalidArgumentException("A maximum of {$maxLocations} stops are allowed.");
        }

        $normalizedStops = $this->normalizeStops($stops);
        $coordinateString = $this->buildCoordinateString($normalizedStops);
        $cacheKey = 'osrm:table:' . hash('sha256', $coordinateString);
        $ttlSeconds = (int) config('services.osrm.table.cache_ttl', 6 * 60 * 60);

        return Cache::remember($cacheKey, $ttlSeconds, function () use ($normalizedStops, $opts) {
            $response = $this->osrmClient->table(array_map(function (array $stop) {
                return [
                    'lat' => $stop['lat'],
                    'lng' => $stop['lng'],
                ];
            }, $normalizedStops), $opts);

            $stopCount = count($normalizedStops);

            $durations = $this->ensureSquareMatrix($response['durations'] ?? null, $stopCount, 'durations');
            $distances = $this->ensureSquareMatrix($response['distances'] ?? null, $stopCount, 'distances');

            return [
                'metadata' => [
                    'provider' => 'osrm',
                    'profile' => 'driving',
                    'units' => [
                        'distance' => 'meters',
                        'duration' => 'seconds',
                    ],
                    'stop_count' => $stopCount,
                    'generated_at' => now()->toIso8601String(),
                ],
                'stops' => $normalizedStops,
                'durations_s' => $durations,
                'distances_m' => $distances,
            ];
        });
    }

    /**
     * @param array<int, array{id: string|int, lat: float|int|string, lng: float|int|string}> $stops
     * @return array<int, array{id: string|int, lat: float, lng: float}>
     */
    private function normalizeStops(array $stops): array
    {
        $normalized = [];

        foreach ($stops as $index => $stop) {
            if (! is_array($stop) || ! array_key_exists('id', $stop) || ! array_key_exists('lat', $stop) || ! array_key_exists('lng', $stop)) {
                throw new InvalidArgumentException("Stop at index {$index} must include id, lat, and lng.");
            }

            $normalized[] = [
                'id' => $stop['id'],
                'lat' => $this->toFloat($stop['lat'], 'lat', $index),
                'lng' => $this->toFloat($stop['lng'], 'lng', $index),
            ];
        }

        return $normalized;
    }

    /**
     * @param array<int, array{id: string|int, lat: float, lng: float}> $stops
     */
    private function buildCoordinateString(array $stops): string
    {
        $parts = [];

        foreach ($stops as $stop) {
            $parts[] = sprintf('%s,%s', $this->normalizeNumber($stop['lng']), $this->normalizeNumber($stop['lat']));
        }

        return implode(';', $parts);
    }

    /**
     * @return array<int, array<int, float>>
     */
    private function ensureSquareMatrix(mixed $matrix, int $size, string $label): array
    {
        if (! is_array($matrix) || count($matrix) !== $size) {
            throw new InvalidArgumentException("{$label} matrix must be an array of {$size} rows.");
        }

        $normalized = [];

        foreach ($matrix as $rowIndex => $row) {
            if (! is_array($row) || count($row) !== $size) {
                throw new InvalidArgumentException("{$label} matrix row {$rowIndex} must contain {$size} entries.");
            }

            $normalizedRow = [];

            foreach ($row as $colIndex => $value) {
                $normalizedRow[] = $this->toFloat($value, "{$label}[{$rowIndex}][{$colIndex}]");
            }

            $normalized[] = $normalizedRow;
        }

        return $normalized;
    }

    private function normalizeNumber(float|int|string $value): string
    {
        return rtrim(rtrim((string) $value, '0'), '.');
    }

    private function toFloat(float|int|string $value, string $field, int $index = null): float
    {
        if (! is_numeric($value)) {
            $context = is_null($index) ? $field : "{$field} at index {$index}";

            throw new InvalidArgumentException("{$context} must be numeric.");
        }

        return (float) $value;
    }
}
