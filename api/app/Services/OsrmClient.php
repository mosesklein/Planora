<?php

namespace App\Services;

use App\Exceptions\OsmrException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

class OsrmClient
{
    private string $baseUrl;

    public function __construct()
    {
        $configuredUrl = config('services.osrm.base_url') ?? config('services.osrm.url');

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

        if (! array_key_exists('code', $data)) {
            throw new RuntimeException('OSRM response missing status code.');
        }

        if ($data['code'] !== 'Ok') {
            $message = $data['message'] ?? 'Unexpected OSRM response code.';

            throw new RuntimeException('OSRM error: ' . $message);
        }

        $route = $data['routes'][0] ?? null;

        if (! is_array($route)) {
            throw new RuntimeException('OSRM response did not include routes.');
        }

        $distance = $route['distance'] ?? null;
        $duration = $route['duration'] ?? null;

        if (! is_numeric($distance) || ! is_numeric($duration)) {
            throw new RuntimeException('OSRM response missing distance or duration.');
        }

        return [
            'distance_meters' => (float) $distance,
            'duration_seconds' => (float) $duration,
            'osrm' => $data,
        ];
    }

    /**
     * @param array<int, array{lat: float|int|string, lng: float|int|string}> $coords
     * @param array<string, mixed> $options
     */
    public function table(array $coords, array $options = []): array
    {
        $coordinateStrings = $this->validateAndFormatLatLngCoordinates($coords);

        $query = array_merge([
            'annotations' => config('services.osrm.table.annotations', 'duration,distance'),
        ], $options);

        $url = $this->baseUrl . '/table/v1/driving/' . implode(';', $coordinateStrings);

        try {
            $response = Http::timeout(10)->get($url, $query);
        } catch (ConnectionException $exception) {
            throw new OsmrException('Failed to reach OSRM service.', 0, null, $exception);
        }

        if (! $response->successful()) {
            throw new OsmrException(
                'OSRM table request failed with status ' . $response->status(),
                $response->status(),
                $response->body()
            );
        }

        $data = $response->json();

        if (! is_array($data)) {
            throw new OsmrException('Invalid JSON response from OSRM.', $response->status(), $response->body());
        }

        if (($data['code'] ?? null) !== 'Ok') {
            $message = $data['message'] ?? 'Unexpected OSRM response code.';

            throw new OsmrException('OSRM error: ' . $message, $response->status(), $data);
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

    /**
     * @param array<int, array{lat: float|int|string, lng: float|int|string}> $coords
     * @return array<int, string>
     */
    private function validateAndFormatLatLngCoordinates(array $coords): array
    {
        $maxLocations = (int) config('services.osrm.table.max_locations', 100);

        if (count($coords) < 2) {
            throw new InvalidArgumentException('At least two coordinates are required.');
        }

        if (count($coords) > $maxLocations) {
            throw new InvalidArgumentException("A maximum of {$maxLocations} coordinates are allowed.");
        }

        $formatted = [];

        foreach ($coords as $index => $coord) {
            if (! is_array($coord) || ! array_key_exists('lat', $coord) || ! array_key_exists('lng', $coord)) {
                throw new InvalidArgumentException("Coordinate at index {$index} must include 'lat' and 'lng'.");
            }

            $lat = $coord['lat'];
            $lng = $coord['lng'];

            if (! $this->isValidLatitude($lat) || ! $this->isValidLongitude($lng)) {
                throw new InvalidArgumentException("Invalid latitude/longitude at index {$index}.");
            }

            $formatted[] = sprintf('%s,%s', $this->normalizeNumber($lng), $this->normalizeNumber($lat));
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
