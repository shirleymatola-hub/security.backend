<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\PoliceStation;
use App\Services\SuperAdmin\OperationalDataService;
use Illuminate\Http\JsonResponse;

class StationController extends Controller
{
    public function __construct(private OperationalDataService $data)
    {
    }

    /** GET /api/super-admin/stations */
    public function index(): JsonResponse
    {
        return response()->json(['stations' => $this->data->stations()]);
    }

    /** GET /api/super-admin/stations/{id} — dados do posto e efetivos. */
    public function show(int $id): JsonResponse
    {
        $station = PoliceStation::with(['district:id,name', 'commander:id,name,phone,email'])
            ->withCount(['officers', 'incidents'])
            ->find($id);

        if (! $station) {
            return response()->json(['message' => 'Posto não encontrado.'], 404);
        }

        $officers = $station->officers()
            ->select('id', 'name', 'email', 'status', 'agent_number', 'police_station_id')
            ->with('roles:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn ($o) => [
                'id' => $o->id,
                'name' => $o->name,
                'email' => $o->email,
                'status' => $o->status,
                'agent_number' => $o->agent_number,
                'roles' => $o->roles->pluck('name')->values()->all(),
            ]);

        return response()->json([
            'station' => [
                'id' => $station->id,
                'name' => $station->name,
                'code' => $station->code,
                'address' => $station->address,
                'phone' => $station->phone,
                'email' => $station->email,
                'latitude' => $station->latitude !== null ? (float) $station->latitude : null,
                'longitude' => $station->longitude !== null ? (float) $station->longitude : null,
                'district' => $station->district ? ['id' => $station->district->id, 'name' => $station->district->name] : null,
                'commander' => $station->commander ? [
                    'id' => $station->commander->id,
                    'name' => $station->commander->name,
                    'phone' => $station->commander->phone,
                    'email' => $station->commander->email,
                ] : null,
                'is_active' => (bool) $station->is_active,
                'officers_count' => $station->officers_count,
                'incidents_count' => $station->incidents_count,
            ],
            'officers' => [
                'officers_count' => $officers->count(),
                'by_role' => $officers->groupBy(fn ($o) => $o['roles'][0] ?? 'sem_role')->map->count(),
                'officers' => $officers->values(),
            ],
        ]);
    }
}
