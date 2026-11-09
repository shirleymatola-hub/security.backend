<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Neighborhood;
use App\Services\IncidentService;
use App\Services\MapService;
use App\Services\SuperAdmin\OperationalDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class CentralMapController extends Controller
{
    public function __construct(
        private OperationalDataService $data,
        private IncidentService $incidents,
        private MapService $map,
    ) {
    }

    /** GET /api/super-admin/map — todas as camadas do Mapa Central de Supervisão. */
    public function index(): JsonResponse
    {
        $errors = [];

        $districts = $this->attempt(fn () => $this->data->districts(), [], 'Não foi possível carregar os distritos.', $errors);
        $stations = $this->attempt(fn () => $this->data->stations(), [], 'Não foi possível carregar os postos policiais.', $errors);

        $neighborhoods = $this->attempt(fn () => Neighborhood::query()
            ->select('id', 'name', 'district_id', 'latitude', 'longitude', 'boundary')
            ->with('district:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn ($n) => [
                'id' => $n->id,
                'name' => $n->name,
                'district_id' => $n->district_id,
                'district_name' => $n->district?->name,
                'latitude' => $n->latitude !== null ? (float) $n->latitude : null,
                'longitude' => $n->longitude !== null ? (float) $n->longitude : null,
                'boundary' => is_string($n->boundary) ? json_decode($n->boundary, true) : $n->boundary,
            ])
            ->all(), [], 'Não foi possível carregar os bairros.', $errors);

        $geoJson = $this->attempt(
            fn () => $this->map->getNeighborhoodsGeoJson(),
            ['type' => 'FeatureCollection', 'features' => []],
            'Não foi possível obter a geometria dos bairros.',
            $errors,
        );

        $incidents = $this->attempt(fn () => array_map(fn ($item) => [
            'id' => $item['id'],
            'reference_code' => $item['reference_code'],
            'title' => $item['title'],
            'status' => $item['status'],
            'priority' => $item['priority'],
            'category' => $item['category']['name'] ?? null,
            'category_color' => $item['category']['color'] ?? null,
            'latitude' => (float) $item['latitude'],
            'longitude' => (float) $item['longitude'],
        ], $this->incidents->getMapData()), [], 'Não foi possível carregar as ocorrências.', $errors);

        $heatmap = $this->attempt(fn () => $this->incidents->getHeatmapData(), [], 'Não foi possível carregar o mapa de calor.', $errors);

        return response()->json([
            'districts' => $districts,
            'stations' => $stations,
            'neighborhoods' => $neighborhoods,
            'neighborhoods_geojson' => $geoJson,
            'incidents' => $incidents,
            'heatmap' => $heatmap,
            'errors' => array_values(array_unique($errors)),
        ]);
    }

    /** Executa uma consulta; em caso de erro devolve o valor por omissão e regista a mensagem. */
    private function attempt(callable $callback, mixed $default, string $message, array &$errors): mixed
    {
        try {
            return $callback();
        } catch (\Throwable $e) {
            Log::error('Super Admin (mapa): '.$message, ['error' => $e->getMessage()]);
            $errors[] = $message;

            return $default;
        }
    }
}
