<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class OpenRouteService
{
    protected string $apiKey;
    protected string $baseUrl = 'https://api.openrouteservice.org/v2';

    public function __construct()
    {
        $this->apiKey = (string) config('services.openrouteservice.api_key', '');
    }

    /**
     * Get directions between two points.
     *
     * @param float  $originLat Origin latitude
     * @param float  $originLng Origin longitude
     * @param float  $destLat   Destination latitude
     * @param float  $destLng   Destination longitude
     * @param string $profile   Travel profile (driving-car, foot-walking, cycling-regular, etc.)
     * @return array Route in GeoJSON format
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
        if (trim($this->apiKey) === '') {
            throw new \Exception('Serviço de roteamento não configurado.');
        }

        $url = "{$this->baseUrl}/directions/{$profile}/geojson";

        $payload = [
            'coordinates' => [
                [$originLng, $originLat],
                [$destLng, $destLat],
            ],
            'instructions' => true,
        ];

        try {
            $response = $this->post($url, $payload);

            // 2010 = ponto sem estrada a menos de 350 m (ocorrências em quintais, mato, etc.):
            // repete deixando o ORS procurar a estrada mais próxima sem limite de raio.
            if ($response->failed() && (int) $response->json('error.code') === 2010) {
                $response = $this->post($url, $payload + ['radiuses' => [-1, -1]]);
            }

            if ($response->failed()) {
                throw new \Exception($this->failureMessage($response));
            }

            $data = $response->json();
            if (!is_array($data) || empty($data['features'][0]['geometry'])) {
                throw new \Exception('Resposta do serviço de roteamento incompleta.');
            }

            return $data;
        } catch (ConnectionException $e) {
            throw new \Exception('Serviço de roteamento indisponível.');
        } catch (RequestException $e) {
            throw new \Exception($this->failureMessage($e->response));
        }
    }

    protected function failureMessage($response): string
    {
        $status = method_exists($response, 'status') ? $response->status() : 0;
        $json = method_exists($response, 'json') ? $response->json() : null;
        $error = is_array($json) ? ($json['error'] ?? null) : null;
        $error = is_array($error) ? $error : [];
        $code = (int) ($error['code'] ?? 0);
        $message = strtolower((string) ($error['message'] ?? ''));

        if ($status === 401 || $status === 403) {
            return 'Falha de autenticação no serviço de roteamento.';
        }

        if ($status === 429) {
            return 'Limite de utilização do serviço de roteamento atingido.';
        }

        if ($status === 404 || $code === 2009 || $code === 2010 || (str_contains($message, 'route') && str_contains($message, 'not found'))) {
            return 'Não foi possível determinar um percurso entre os pontos indicados.';
        }

        return 'Erro ao comunicar com o serviço de roteamento.';
    }

    protected function post(string $url, array $payload): \Illuminate\Http\Client\Response
    {
        return Http::withHeaders([
            'Authorization' => $this->apiKey,
            'Content-Type' => 'application/json',
        ])->timeout(60)->post($url, $payload);
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
            throw new \Exception('Resposta do serviço de roteamento incompleta.');
        }

        return [
            'distance' => $segment['distance'],
            'duration' => $segment['duration'],
        ];
    }
}