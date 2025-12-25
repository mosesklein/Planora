<?php

namespace App\Http\Controllers;

use App\Services\OsrmClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use RuntimeException;

class OsrmController extends Controller
{
    public function __construct(private OsrmClient $osrmClient)
    {
    }

    public function route(Request $request): JsonResponse
    {
        try {
            $from = $this->parseCoordinate($request->query('from'), 'from');
            $to = $this->parseCoordinate($request->query('to'), 'to');

            $result = $this->osrmClient->route([$from, $to]);

            return response()->json([
                'distance_meters' => $result['distance_meters'],
                'duration_seconds' => $result['duration_seconds'],
                'osrm' => $result['osrm'],
            ]);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['error' => $exception->getMessage()], 422);
        } catch (RuntimeException $exception) {
            return response()->json(['error' => $exception->getMessage()], 502);
        }
    }

    /**
     * @return array{0: float, 1: float}
     */
    private function parseCoordinate(?string $value, string $label): array
    {
        if (! is_string($value) || strpos($value, ',') === false) {
            throw new InvalidArgumentException("{$label} must be provided as 'lon,lat'.");
        }

        [$lon, $lat] = array_map('trim', explode(',', $value, 2));

        if ($lon === '' || $lat === '' || ! is_numeric($lon) || ! is_numeric($lat)) {
            throw new InvalidArgumentException("{$label} must contain valid numeric longitude and latitude.");
        }

        return [(float) $lon, (float) $lat];
    }
}
