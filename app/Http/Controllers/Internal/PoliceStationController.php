<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\District;
use App\Models\PoliceStation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PoliceStationController extends Controller
{
    public function index(): JsonResponse
    {
        $stations = PoliceStation::where('is_active', true)
            ->select('id', 'name', 'code', 'address', 'phone', 'email', 'latitude', 'longitude', 'district_id', 'commander_id', 'is_active')
            ->with('district:id,name')
            ->with('commander:id,name')
            ->withCount('officers')
            ->withCount('incidents')
            ->orderBy('name')
            ->get()
            ->map(fn ($station) => [
                'id' => $station->id,
                'name' => $station->name,
                'code' => $station->code,
                'address' => $station->address,
                'phone' => $station->phone,
                'email' => $station->email,
                'latitude' => $station->latitude ? (float) $station->latitude : null,
                'longitude' => $station->longitude ? (float) $station->longitude : null,
                'district_id' => $station->district_id,
                'district_name' => $station->district?->name,
                'commander_id' => $station->commander_id,
                'commander' => $station->commander ? [
                    'id' => $station->commander->id,
                    'name' => $station->commander->name,
                ] : null,
                'is_active' => $station->is_active,
                'officers_count' => $station->officers_count,
                'incidents_count' => $station->incidents_count,
            ]);

        return response()->json([
            'success' => true,
            'data' => $stations,
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $station = PoliceStation::with([
            'district:id,name',
            'commander:id,name,phone,email',
        ])
            ->withCount('officers')
            ->withCount('incidents')
            ->find($id);

        if (!$station) {
            return response()->json([
                'success' => false,
                'message' => 'Posto não encontrado.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $station->id,
                'name' => $station->name,
                'code' => $station->code,
                'address' => $station->address,
                'phone' => $station->phone,
                'email' => $station->email,
                'latitude' => $station->latitude ? (float) $station->latitude : null,
                'longitude' => $station->longitude ? (float) $station->longitude : null,
                'district_id' => $station->district_id,
                'district' => $station->district ? [
                    'id' => $station->district->id,
                    'name' => $station->district->name,
                ] : null,
                'commander_id' => $station->commander_id,
                'commander' => $station->commander ? [
                    'id' => $station->commander->id,
                    'name' => $station->commander->name,
                    'phone' => $station->commander->phone,
                    'email' => $station->commander->email,
                ] : null,
                'is_active' => $station->is_active,
                'officers_count' => $station->officers_count,
                'incidents_count' => $station->incidents_count,
                'created_at' => $station->created_at,
                'updated_at' => $station->updated_at,
            ],
        ]);
    }

    public function officers(int $id): JsonResponse
    {
        $station = PoliceStation::find($id);

        if (!$station) {
            return response()->json([
                'success' => false,
                'message' => 'Posto não encontrado.',
            ], 404);
        }

        $officers = $station->officers()
            ->select('id', 'name', 'email', 'status', 'agent_number', 'police_station_id')
            ->get()
            ->map(fn ($officer) => [
                'id' => $officer->id,
                'name' => $officer->name,
                'email' => $officer->email,
                'status' => $officer->status,
                'agent_number' => $officer->agent_number,
                'police_station_id' => $officer->police_station_id,
                'roles' => $officer->getRoleNames()->toArray(),
            ]);

        $byRole = $officers->groupBy(fn ($o) => $o['roles'][0] ?? 'sem_role')
            ->map(fn ($group) => $group->count());

        return response()->json([
            'success' => true,
            'data' => [
                'station_id' => $station->id,
                'station_name' => $station->name,
                'officers_count' => $officers->count(),
                'by_role' => $byRole,
                'officers' => $officers,
            ],
        ]);
    }

    public function districts(): JsonResponse
    {
        $districts = District::select('id', 'name')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $districts,
        ]);
    }
}
