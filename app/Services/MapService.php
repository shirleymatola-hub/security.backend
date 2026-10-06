<?php

namespace App\Services;

use App\Models\Incident;
use App\Models\PoliceStation;
use App\Models\District;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class MapService
{
    /**
     * Get tile URL for OpenStreetMap
     */
    public function getTileUrl(): string
    {
        return config('map.tile_url');
    }

    /**
     * Get default center coordinates (Matola C)
     */
    public function getDefaultCenter(): array
    {
        return config('map.default_center');
    }

    /**
     * Get all incidents as GeoJSON for Leaflet
     */
    public function getIncidentsGeoJson(?int $stationId = null, array $filters = [], ?int $assignedTo = null): array
    {
        $bounds = config('map.bounds');
        $query = Incident::whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->where('is_public', true);

        if ($bounds) {
            $query->whereBetween('latitude', [$bounds['south_west']['lat'], $bounds['north_east']['lat']])
                ->whereBetween('longitude', [$bounds['south_west']['lng'], $bounds['north_east']['lng']]);
        }

        if ($assignedTo) {
            $query->where('assigned_to', $assignedTo);
        } elseif ($stationId) {
            $query->where('police_station_id', $stationId);
        }

        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (!empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['neighborhood'])) {
            $query->where('neighborhood', $filters['neighborhood']);
        }

        $incidents = $query->with('category:id,name,slug,color,icon')->get();


        $features = $incidents->map(function ($incident) {
            $category = $incident->category;

            return [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [
                        (float) $incident->longitude,
                        (float) $incident->latitude,
                    ],
                ],
                'properties' => [
                    'id' => $incident->id,
                    'reference_code' => $incident->reference_code,
                    'title' => $incident->title,
                    'description' => $incident->description,
                    'priority' => $incident->priority,
                    'status' => $incident->status,
                    'category' => $category?->name,
                    'category_color' => $category?->color ?? '#115cb9',
                    'category_icon' => $category?->icon ?? 'warning',
                    'address' => $incident->address_detail,
                    'neighborhood' => $incident->neighborhood,
                    'incident_date' => $incident->incident_date?->format('d/m/Y H:i'),
                    'marker_color' => config("map.marker_colors.{$incident->priority}", '#115cb9'),
                ],
            ];
        });

        return [
            'type' => 'FeatureCollection',
            'features' => $features->toArray(),
        ];
    }

    /**
     * Get police stations as GeoJSON
     */
    public function getStationsGeoJson(?int $stationId = null, bool $mainOnly = false): array
    {
        $query = PoliceStation::where('is_active', true)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->withinStudyArea()
            ->with('district:id,name');

        if ($stationId) {
            $query->where('id', $stationId);
        }

        if ($mainOnly) {
            $mainIds = [2, 7, 10, 11];
            $query->whereIn('id', $mainIds)
                ->where('tipo_posto', 'Esquadra da PRM');
        } else {
            $query->where('tipo_posto', 'Esquadra da PRM');
        }

        $stations = $query->get();

        $features = $stations->map(function ($station) {
            return [
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
                    'tipo_posto' => $station->tipo_posto,
                ],
            ];
        });

        return [
            'type' => 'FeatureCollection',
            'features' => $features->toArray(),
        ];
    }

    /**
     * Get SERNIC unit as GeoJSON (police stations with tipo_posto = 'Investigação Criminal')
     */
    public function getSernicGeoJson(): array
    {
        $stations = PoliceStation::where('is_active', true)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->where('tipo_posto', 'Investigação Criminal')
            ->withinStudyArea()
            ->with('district:id,name')
            ->get();

        $features = $stations->map(function ($station) {
            return [
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
                    'tipo_posto' => $station->tipo_posto,
                ],
            ];
        });

        return [
            'type' => 'FeatureCollection',
            'features' => $features->toArray(),
        ];
    }

    /**
     * Get heatmap data (lat/lng pairs)
     */
    public function getHeatmapData(?int $stationId = null): array
    {
        $bounds = config('map.bounds');
        $query = Incident::whereNotNull('latitude')
            ->whereNotNull('longitude');

        if ($bounds) {
            $query->whereBetween('latitude', [$bounds['south_west']['lat'], $bounds['north_east']['lat']])
                ->whereBetween('longitude', [$bounds['south_west']['lng'], $bounds['north_east']['lng']]);
        }

        if ($stationId) {
            $query->where('police_station_id', $stationId);
        }

        return $query->get()
            ->map(fn($incident) => [
                'lat' => (float) $incident->latitude,
                'lng' => (float) $incident->longitude,
                'intensity' => match ($incident->priority) {
                    'urgent' => 1.0,
                    'high' => 0.8,
                    'medium' => 0.5,
                    'low' => 0.3,
                    default => 0.5,
                },
            ])
            ->toArray();
    }

    /**
     * Get district boundary as polygon coordinates
     */
    public function getDistrictBoundary(string $districtName): ?array
    {
        $district = District::where('name', $districtName)->first();

        if ($district && $district->boundary) {
            return json_decode($district->boundary, true);
        }

        return null;
    }

    /**
     * Get all neighborhoods as GeoJSON using PostGIS geometry column
     */
    public function getNeighborhoodsGeoJson(): array
    {
        $rows = DB::select("
            SELECT id, name, ST_AsGeoJSON(geometry) AS geometry
            FROM neighborhoods
            WHERE geometry IS NOT NULL
        ");

        $features = array_map(fn($row) => [
            'type' => 'Feature',
            'id' => $row->id,
            'geometry' => json_decode($row->geometry, true),
            'properties' => [
                'id' => $row->id,
                'name' => $row->name,
            ],
        ], $rows);

        return [
            'type' => 'FeatureCollection',
            'features' => $features,
        ];
    }

    /**
     * Get station coverage buffer (1km) as GeoJSON Feature from the police_station_coverage view
     */
    public function getStationCoverage(int $stationId): ?array
    {
        $rows = DB::select("
            SELECT id, name, code, address,
                   ST_AsGeoJSON(buffer_1km) AS buffer_1km
            FROM police_station_coverage
            WHERE id = ?
        ", [$stationId]);

        if (empty($rows)) {
            return null;
        }

        $row = $rows[0];

        return [
            'type' => 'Feature',
            'geometry' => json_decode($row->buffer_1km, true),
            'properties' => [
                'station_id' => $row->id,
                'station_name' => $row->name,
                'station_code' => $row->code,
                'radius' => '1000m',
            ],
        ];
    }

    /**
     * Get all active stations coverage buffers (1km) as GeoJSON FeatureCollection
     */
    public function getAllStationsCoverage(): array
    {
        $studyArea = config('map.study_area');
        $sql = "SELECT psc.id, psc.name, psc.code, psc.address,
                   ST_AsGeoJSON(psc.buffer_1km) AS buffer_1km
                FROM police_station_coverage psc
                JOIN police_stations ps ON ps.id = psc.id
                WHERE psc.is_active = true";
        $bindings = [];

        if ($studyArea) {
            $sql .= " AND ps.latitude BETWEEN ? AND ? AND ps.longitude BETWEEN ? AND ?";
            $bindings[] = $studyArea['south_west']['lat'];
            $bindings[] = $studyArea['north_east']['lat'];
            $bindings[] = $studyArea['south_west']['lng'];
            $bindings[] = $studyArea['north_east']['lng'];
        }

        $rows = DB::select($sql, $bindings);

        $features = array_map(fn($row) => [
            'type' => 'Feature',
            'geometry' => json_decode($row->buffer_1km, true),
            'properties' => [
                'station_id' => $row->id,
                'station_name' => $row->name,
                'station_code' => $row->code,
                'address' => $row->address,
                'radius' => '1000m',
            ],
        ], $rows);

        return [
            'type' => 'FeatureCollection',
            'features' => $features,
        ];
    }

    /**
     * Reverse geocode using Nominatim (OpenStreetMap)
     */
    public function reverseGeocode(float $lat, float $lng): ?array
    {
        $response = Http::timeout(10)->get('https://nominatim.openstreetmap.org/reverse', [
            'format' => 'json',
            'lat' => $lat,
            'lon' => $lng,
            'addressdetails' => 1,
            'accept-language' => 'pt',
        ]);

        if ($response->successful()) {
            $data = $response->json();
            return [
                'display_name' => $data['display_name'] ?? null,
                'address' => $data['address'] ?? null,
            ];
        }

        return null;
    }
}