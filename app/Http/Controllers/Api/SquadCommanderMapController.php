<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PoliceStation;
use App\Services\MapService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SquadCommanderMapController extends Controller
{
    public function __construct(
        protected MapService $mapService
    ) {}

    private function getCommanderStation()
    {
        $stationId = Auth::user()->police_station_id;
        if (!$stationId) {
            abort(403, 'Nenhuma esquadra associada ao seu utilizador.');
        }
        return PoliceStation::findOrFail($stationId);
    }

    public function incidents(): JsonResponse
    {
        $station = $this->getCommanderStation();
        $geoJson = $this->mapService->getIncidentsGeoJson($station->id);

        return response()->json($geoJson);
    }

    public function station(): JsonResponse
    {
        $station = $this->getCommanderStation();

        $feature = [
            'type' => 'Feature',
            'geometry' => [
                'type' => 'Point',
                'coordinates' => [
                    (float) $station->longitude,
                    (float) $station->latitude,
                ],
            ],
            'properties' => [
                'id' => $station->id,
                'name' => $station->name,
                'code' => $station->code,
                'address' => $station->address,
                'phone' => $station->phone,
                'email' => $station->email,
                'district' => $station->district?->name,
                'commander' => $station->commander?->name,
            ],
        ];

        return response()->json([
            'type' => 'FeatureCollection',
            'features' => [$feature],
        ]);
    }

    public function stationCoverage(): JsonResponse
    {
        $station = $this->getCommanderStation();
        $coverage = $this->mapService->getStationCoverage($station->id);

        if ($coverage) {
            return response()->json($coverage);
        }

        return response()->json(null);
    }
}
