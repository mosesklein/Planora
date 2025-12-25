<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

class OsrmClient
{
    private string $baseUrl;

    public function __construct()
    {
        $configuredUrl = config('services.osrm.url');

        if (empty($configuredUrl)) {
            throw new RuntimeException('OSRM base URL is not configured.');
        }

        $this->baseUrl = rtrim($configuredUrl, '/');
    }

    /**
     * @param array<int, array{0: float|int|string, 1: float|int|string}> $coords
     * @param array<string, mixed> $options
     */
    public function route(array $coords, array $options = []): array
    {
        $coordinateStrings = $this->validateAndFormatCoordinates($coords);

        $query = array_merge([
            'overview' => 'false',
            'steps' => 'false',
        ], $options);

        $url = $this->baseUrl . '/route/v1/driving/' . implode(';', $coordinateStrings);

        try {
            $response = Http::timeout(10)->get($url, $query);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('Failed to reach OSRM service.', 0, $exception);
        }

        if (! $response->successful()) {
            throw new RuntimeException('OSRM request failed with status ' . $response->status());
        }

        $data = $response->json();

        if (! is_array($data)) {
            throw new RuntimeException('Invalid JSON response from OSRM.');
        }

        if (($data['code'] ?? null) !== 'Ok') {
            $message = $data['message'] ?? 'Unexpected OSRM response code.';

            throw new RuntimeException('OSRM error: ' . $message);
        }

        return $data;
    }

    /**
     * @param array<int, array{0: float|int|string, 1: float|int|string}> $coords
     * @return array<int, string>
     */
    private function validateAndFormatCoordinates(array $coords): array
    {
        if (count($coords) < 2) {
            throw new InvalidArgumentException('At least two coordinates are required.');
        }

        $formatted = [];

        foreach ($coords as $index => $coord) {
            if (! is_array($coord) || count($coord) !== 2) {
                throw new InvalidArgumentException("Coordinate at index {$index} must be an array with longitude and latitude.");
            }

            [$lon, $lat] = $coord;

            if (! $this->isValidLongitude($lon) || ! $this->isValidLatitude($lat)) {
                throw new InvalidArgumentException("Invalid longitude/latitude at index {$index}.");
            }

            $formatted[] = sprintf('%s,%s', $this->normalizeNumber($lon), $this->normalizeNumber($lat));
        }

        return $formatted;
    }

    private function isValidLongitude(mixed $value): bool
    {
        return is_numeric($value) && $value >= -180 && $value <= 180;
    }

    private function isValidLatitude(mixed $value): bool
    {
        return is_numeric($value) && $value >= -90 && $value <= 90;
    }

    private function normalizeNumber(float|int|string $value): string
    {
        return rtrim(rtrim((string) $value, '0'), '.');
    }
}
