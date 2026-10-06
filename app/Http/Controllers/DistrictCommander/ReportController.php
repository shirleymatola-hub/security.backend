<?php

namespace App\Http\Controllers\DistrictCommander;

use App\Http\Controllers\Controller;
use App\Models\District;
use App\Models\Incident;
use App\Models\PoliceStation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;

class ReportController extends Controller
{
    public function index(): JsonResponse
    {
        $user = auth()->user();
        $districtId = $user->district_id;
        $stationIds = PoliceStation::where('district_id', $districtId)->withinStudyArea()->pluck('id');

        $year = (int) request('year', now()->year);
        $month = request('month', '');
        $isAllTime = ($month === '' || $month === '0' || $month === 0);

        if ($isAllTime) {
            $baseQuery = Incident::whereIn('police_station_id', $stationIds);
        } else {
            $month = (int) $month;
            $baseQuery = Incident::whereIn('police_station_id', $stationIds)
                ->whereMonth('incidents.created_at', $month)
                ->whereYear('incidents.created_at', $year);
        }

        $total = (clone $baseQuery)->count();
        $pending = (clone $baseQuery)->where('status', 'pending')->count();
        $investigating = (clone $baseQuery)->where('status', 'investigating')->count();
        $resolved = (clone $baseQuery)->where('status', 'resolved')->count();
        $archived = (clone $baseQuery)->where('status', 'archived')->count();
        $resolutionRate = $total > 0 ? round(($resolved / $total) * 100, 1) : 0;

        $byCategory = (clone $baseQuery)
            ->join('categories', 'incidents.category_id', '=', 'categories.id')
            ->select('categories.name as category', DB::raw('count(*) as count'))
            ->groupBy('categories.name')
            ->orderByDesc('count')
            ->get();

        $byPriority = (clone $baseQuery)
            ->select('priority', DB::raw('count(*) as count'))
            ->groupBy('priority')
            ->get()
            ->map(fn($row) => [
                'priority' => $row->priority,
                'count' => $row->count,
                'label' => match($row->priority) {
                    'urgent' => 'Urgente',
                    'high' => 'Alta',
                    'medium' => 'Média',
                    'low' => 'Baixa',
                },
            ]);

        $byNeighborhood = (clone $baseQuery)
            ->whereNotNull('neighborhood')
            ->select('neighborhood', DB::raw('count(*) as count'))
            ->groupBy('neighborhood')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        $monthLabels = [
            1 => 'Jan', 2 => 'Fev', 3 => 'Mar', 4 => 'Abr', 5 => 'Mai', 6 => 'Jun',
            7 => 'Jul', 8 => 'Ago', 9 => 'Set', 10 => 'Out', 11 => 'Nov', 12 => 'Dez',
        ];

        if ($isAllTime) {
            $yearTrend = Incident::whereIn('police_station_id', $stationIds)
                ->selectRaw("EXTRACT(MONTH FROM incidents.created_at)::int as month_number, count(*) as total")
                ->groupBy('month_number')
                ->pluck('total', 'month_number')
                ->toArray();
        } else {
            $yearTrend = Incident::whereIn('police_station_id', $stationIds)
                ->whereYear('incidents.created_at', $year)
                ->selectRaw("EXTRACT(MONTH FROM incidents.created_at)::int as month_number, count(*) as total")
                ->groupBy('month_number')
                ->pluck('total', 'month_number')
                ->toArray();
        }
        $trend = collect(range(1, 12))->map(fn($m) => $yearTrend[$m] ?? 0)->toArray();

        if ($isAllTime) {
            $stations = PoliceStation::where('district_id', $districtId)
                ->withinStudyArea()
                ->where('is_active', true)
                ->where('tipo_posto', 'Esquadra da PRM')
                ->with('commander')
                ->withCount('incidents')
                ->orderByDesc('incidents_count')
                ->get();

            $stationStatuses = [];
            foreach ($stations as $station) {
                $stationStatuses[$station->id] = Incident::where('police_station_id', $station->id)
                    ->select('status', DB::raw('count(*) as count'))
                    ->groupBy('status')
                    ->pluck('count', 'status')
                    ->toArray();
            }
        } else {
            $stations = PoliceStation::where('district_id', $districtId)
                ->withinStudyArea()
                ->where('is_active', true)
                ->where('tipo_posto', 'Esquadra da PRM')
                ->with('commander')
                ->withCount(['incidents' => function ($q) use ($year, $month) {
                    $q->whereMonth('incidents.created_at', $month)->whereYear('incidents.created_at', $year);
                }])
                ->orderByDesc('incidents_count')
                ->get();

            $stationStatuses = [];
            foreach ($stations as $station) {
                $stationStatuses[$station->id] = Incident::where('police_station_id', $station->id)
                    ->whereMonth('incidents.created_at', $month)
                    ->whereYear('incidents.created_at', $year)
                    ->select('status', DB::raw('count(*) as count'))
                    ->groupBy('status')
                    ->pluck('count', 'status')
                    ->toArray();
            }
        }

        $incidentsGeoQuery = Incident::whereIn('police_station_id', $stationIds)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->with('category:id,name', 'policeStation:id,name');

        if (!$isAllTime) {
            $incidentsGeoQuery->whereMonth('incidents.created_at', $month)
                ->whereYear('incidents.created_at', $year);
        }

        $incidentsGeoJson = $incidentsGeoQuery->get()
            ->map(fn($i) => [
                'type' => 'Feature',
                'geometry' => ['type' => 'Point', 'coordinates' => [(float) $i->longitude, (float) $i->latitude]],
                'properties' => [
                    'id' => $i->id,
                    'reference_code' => $i->reference_code,
                    'title' => $i->title,
                    'category' => $i->category?->name ?? '',
                    'priority' => $i->priority,
                    'status' => $i->status,
                    'neighborhood' => $i->neighborhood ?? '',
                    'station_name' => $i->policeStation?->name ?? '',
                ],
            ]);

        $totalWithCoords = (clone $baseQuery)->whereNotNull('latitude')->whereNotNull('longitude')->count();
        $totalWithoutCoords = $total - $totalWithCoords;

        $years = range(now()->year - 5, now()->year);

        $reportStationsJson = $stations->filter(fn($s) => $s->latitude && $s->longitude)
            ->map(fn($s) => [
                'name' => $s->name,
                'lat' => (float) $s->latitude,
                'lng' => (float) $s->longitude,
                'address' => $s->address,
                'code' => $s->code,
            ])
            ->values()
            ->toArray();

        $district = District::find($districtId);
        $districtBoundary = null;
        if ($district) {
            $row = DB::selectOne("SELECT ST_AsGeoJSON(geometry) AS geometry FROM districts WHERE id = ?", [$districtId]);
            if ($row) {
                $districtBoundary = [
                    'type' => 'Feature',
                    'geometry' => json_decode($row->geometry, true),
                    'properties' => ['name' => $district->name],
                ];
            }
        }

        $neighborhoods = [];
        $neighborhoodRows = DB::select("
            SELECT id, name, ST_AsGeoJSON(geometry) AS geometry
            FROM neighborhoods
            WHERE district_id = ? AND geometry IS NOT NULL
        ", [$districtId]);
        foreach ($neighborhoodRows as $row) {
            $neighborhoods[] = [
                'type' => 'Feature',
                'id' => $row->id,
                'geometry' => json_decode($row->geometry, true),
                'properties' => ['id' => $row->id, 'name' => $row->name],
            ];
        }

        return response()->json([
            'total' => $total,
            'pending' => $pending,
            'investigating' => $investigating,
            'resolved' => $resolved,
            'archived' => $archived,
            'resolutionRate' => $resolutionRate,
            'byCategory' => $byCategory,
            'byPriority' => $byPriority,
            'byNeighborhood' => $byNeighborhood,
            'trend' => $trend,
            'monthLabels' => $monthLabels,
            'stations' => $stations,
            'stationStatuses' => $stationStatuses,
            'incidentsGeoJson' => $incidentsGeoJson->values(),
            'reportStationsJson' => $reportStationsJson,
            'districtBoundary' => $districtBoundary,
            'neighborhoods' => $neighborhoods,
            'year' => $year,
            'month' => $month,
            'isAllTime' => $isAllTime,
            'years' => $years,
            'totalWithoutCoords' => $totalWithoutCoords,
            // Subtítulo de impressão do mapa (a view usava auth()->user()->district->name, sempre vazio).
            'districtName' => $district?->name ?? '',
        ]);
    }

    /**
     * Antes redirecionava para reports.index com os filtros validados;
     * agora devolve os filtros (o frontend navega para /district-commander/reports).
     */
    public function generate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'year' => 'required|integer|min:2020|max:' . (now()->year + 1),
            'month' => 'required',
        ]);

        return response()->json($validated);
    }

    public function exportPdf()
    {
        $user = auth()->user();
        $districtId = $user->district_id;
        $stationIds = PoliceStation::where('district_id', $districtId)->withinStudyArea()->pluck('id');
        $year = (int) request('year', now()->year);
        $month = request('month', '');
        $isAllTime = ($month === '' || $month === '0' || $month === 0);

        if ($isAllTime) {
            $baseQuery = Incident::whereIn('police_station_id', $stationIds);
        } else {
            $month = (int) $month;
            $baseQuery = Incident::whereIn('police_station_id', $stationIds)
                ->whereMonth('incidents.created_at', $month)
                ->whereYear('incidents.created_at', $year);
        }

        $report = [
            'total' => (clone $baseQuery)->count(),
            'pending' => (clone $baseQuery)->where('status', 'pending')->count(),
            'investigating' => (clone $baseQuery)->where('status', 'investigating')->count(),
            'resolved' => (clone $baseQuery)->where('status', 'resolved')->count(),
        ];
        $report['resolution_rate'] = $report['total'] > 0
            ? round(($report['resolved'] / $report['total']) * 100, 1)
            : 0;

        $pdf = Pdf::loadView('district_commander.reports.pdf', [
            'report' => $report,
            'districtId' => $districtId,
            'district' => District::find($districtId),
            'year' => $year,
            'month' => $month,
            'isAllTime' => $isAllTime,
        ]);

        $filename = "relatorio_distrito_{$districtId}_{$year}_{$month}.pdf";
        return $pdf->download($filename);
    }
}
