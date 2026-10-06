<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\RequestException;

class OpenRouteService
{
    protected string $apiKey;
    protected string $baseUrl = 'https://api.openrouteservice.org/v2';

    public function __construct()
    {
        $this->apiKey = config('services.openrouteservice.api_key');
    }

    /**
     * Get directions between two points.
     *
     * @param float $originLat  Origin latitude
     * @param float $originLng  Origin longitude
     * @param float $destLat    Destination latitude
     * @param float $destLng    Destination longitude
     * @param string $profile   Travel profile (driving-car, foot-walking, cycling-regular, etc.)
     * @return array            Route in GeoJSON format
     *
     * @throws \Exception
     */
    public function getDirections(
        float $originLat,
        float $originLng,
        float $destLat,
        float $destLng,
        string $profile = 'driving-car'
    ): array {
        $url = "{$this->baseUrl}/directions/{$profile}/geojson";

        $payload = [
            'coordinates' => [
                [$originLng, $originLat],
                [$destLng, $destLat],
            ],
            'instructions' => true,
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(60)->post($url, $payload);

            if ($response->failed()) {
                $error = $response->json('error', 'Unknown error');
                $message = $response->json('error.message', $error);

                throw new \Exception("OpenRouteService API error: {$message}");
            }

            return $response->json();
        } catch (RequestException $e) {
            $body = $e->response->json('error', []);
            $message = is_array($body) ? ($body['message'] ?? $e->getMessage()) : $e->getMessage();

            throw new \Exception("OpenRouteService request failed: {$message}");
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Get the distance (meters) and duration (seconds) for a route.
     */
    public function getRouteSummary(
        float $originLat,
        float $originLng,
        float $destLat,
        float $destLng,
        string $profile = 'driving-car'
    ): array {
        $geojson = $this->getDirections($originLat, $originLng, $destLat, $destLng, $profile);

        $segment = $geojson['features'][0]['properties']['segments'][0] ?? null;

        if (!$segment) {
            throw new \Exception('No route summary available.');
        }

        return [
            'distance' => $segment['distance'],
            'duration' => $segment['duration'],
        ];
    }
}