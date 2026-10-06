<?php

namespace App\Http\Controllers\DistrictCommander;

use App\Http\Controllers\Controller;
use App\Models\Incident;
use App\Models\PoliceStation;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class StationController extends Controller
{
    public function show(PoliceStation $station): JsonResponse
    {
        $user = auth()->user();
        $districtId = $user->district_id;

        if ($station->district_id !== $districtId) {
            abort(403);
        }

        $station->load('commander');
        $station->loadCount(['incidents', 'officers']);

        $officers = $station->officers()
            ->where('status', 'active')
            ->get();

        $incidents = Incident::where('police_station_id', $station->id)
            ->with('category:id,name')
            ->latest()
            ->paginate(15);

        $stats = [
            'total' => $station->incidents_count,
            'pending' => Incident::where('police_station_id', $station->id)->where('status', 'pending')->count(),
            'investigating' => Incident::where('police_station_id', $station->id)->where('status', 'investigating')->count(),
            'resolved' => Incident::where('police_station_id', $station->id)->where('status', 'resolved')->count(),
        ];

        $byPriority = Incident::where('police_station_id', $station->id)
            ->select('priority', DB::raw('count(*) as total'))
            ->groupBy('priority')
            ->pluck('total', 'priority')
            ->toArray();

        $byCategory = Incident::where('police_station_id', $station->id)
            ->join('categories', 'incidents.category_id', '=', 'categories.id')
            ->select('categories.name as category', DB::raw('count(*) as count'))
            ->groupBy('categories.name')
            ->orderByDesc('count')
            ->get();

        return response()->json(compact(
            'station',
            'officers',
            'incidents',
            'stats',
            'byPriority',
            'byCategory'
        ));
    }
}
