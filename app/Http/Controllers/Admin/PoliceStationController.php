<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PoliceStation;
use App\Models\District;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PoliceStationController extends Controller
{
    public function index(): JsonResponse
    {
        $stations = PoliceStation::with(['district', 'commander'])
            ->orderBy('id', 'asc')
            ->paginate(15);

        return response()->json([
            'stations' => $stations,
        ]);
    }

    /**
     * GET /api/admin/stations/create — dados do formulário de criação.
     */
    public function create(): JsonResponse
    {
        return response()->json([
            'districts' => District::all(),
            'managers' => User::role('manager')->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|unique:police_stations',
            'address' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'district_id' => 'required|exists:districts,id',
            'commander_id' => 'nullable|exists:users,id',
        ]);

        $station = PoliceStation::create($validated);

        return response()->json([
            'message' => 'Posto policial criado com sucesso.',
            'station' => $station,
        ], 201);
    }

    /**
     * GET /api/admin/stations/{station}/edit — posto e dados do formulário.
     */
    public function edit(PoliceStation $station): JsonResponse
    {
        return response()->json([
            'station' => $station,
            'districts' => District::all(),
            'managers' => User::role('manager')->get(),
        ]);
    }

    public function update(Request $request, PoliceStation $station): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => "required|string|unique:police_stations,code,{$station->id}",
            'address' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'district_id' => 'required|exists:districts,id',
            'commander_id' => 'nullable|exists:users,id',
            'is_active' => 'boolean',
        ]);

        $station->update($validated);

        return response()->json([
            'message' => 'Posto policial atualizado com sucesso.',
            'station' => $station->fresh(),
        ]);
    }

    public function show(PoliceStation $station): JsonResponse
    {
        $station->load(['district', 'commander', 'officers', 'incidents' => function ($q) {
            $q->latest('incident_date')->limit(10);
        }]);

        $station->loadCount(['incidents', 'officers']);

        return response()->json([
            'station' => $station,
        ]);
    }

    public function destroy(PoliceStation $station): JsonResponse
    {
        $station->delete();

        return response()->json(['message' => 'Posto policial eliminado com sucesso.']);
    }
}
