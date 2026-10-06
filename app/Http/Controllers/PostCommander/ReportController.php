<?php

namespace App\Http\Controllers\PostCommander;

use App\Http\Controllers\Controller;
use App\Models\Incident;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;

class ReportController extends Controller
{
    public function index(): JsonResponse
    {
        $stationId = auth()->user()->police_station_id;
        $year = (int) request('year', now()->year);
        $month = (int) request('month', now()->month);

        $baseQuery = Incident::where('incidents.police_station_id', $stationId)
            ->whereMonth('incidents.created_at', $month)
            ->whereYear('incidents.created_at', $year);

        $total = (clone $baseQuery)->count();
        $pending = (clone $baseQuery)->where('incidents.status', 'pending')->count();
        $investigating = (clone $baseQuery)->where('incidents.status', 'investigating')->count();
        $resolved = (clone $baseQuery)->where('incidents.status', 'resolved')->count();
        $archived = (clone $baseQuery)->where('incidents.status', 'archived')->count();
        $urgent = (clone $baseQuery)->where('incidents.priority', 'urgent')->where('incidents.status', '!=', 'resolved')->count();
        $resolutionRate = $total > 0 ? round(($resolved / $total) * 100, 1) : 0;

        $byCategory = (clone $baseQuery)
            ->join('categories', 'incidents.category_id', '=', 'categories.id')
            ->select('categories.name as category', DB::raw('count(*) as count'))
            ->groupBy('categories.name')
            ->orderByDesc('count')
            ->get();

        $byPriority = (clone $baseQuery)
            ->select('incidents.priority', DB::raw('count(*) as count'))
            ->groupBy('incidents.priority')
            ->pluck('count', 'priority')
            ->toArray();

        $byPriorityFormatted = [];
        foreach (['urgent', 'high', 'medium', 'low'] as $p) {
            $byPriorityFormatted[] = [
                'priority' => $p,
                'count' => $byPriority[$p] ?? 0,
                'label' => match($p) {
                    'urgent' => 'Urgente',
                    'high' => 'Alta',
                    'medium' => 'Média',
                    'low' => 'Baixa',
                },
            ];
        }

        $byNeighborhood = (clone $baseQuery)
            ->whereNotNull('incidents.neighborhood')
            ->select('incidents.neighborhood', DB::raw('count(*) as count'))
            ->groupBy('incidents.neighborhood')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        $byHour = (clone $baseQuery)
            ->whereNotNull('incidents.created_at')
            ->selectRaw("EXTRACT(HOUR FROM incidents.created_at)::int as hour, count(*) as count")
            ->groupBy('hour')
            ->orderBy('hour')
            ->pluck('count', 'hour')
            ->toArray();

        $hourlyData = collect(range(0, 23))->map(fn($h) => $byHour[$h] ?? 0)->toArray();

        $monthlyTrend = Incident::where('incidents.police_station_id', $stationId)
            ->whereYear('incidents.created_at', $year)
            ->selectRaw("EXTRACT(MONTH FROM incidents.created_at)::int as month_number, count(*) as total")
            ->groupBy('month_number')
            ->pluck('total', 'month_number')
            ->toArray();

        $agentPerformance = User::where('users.police_station_id', $stationId)
            ->role('police')
            ->leftJoin('incidents', 'users.id', '=', 'incidents.assigned_to')
            ->where(function ($q) use ($year, $month) {
                $q->whereNull('incidents.id')
                  ->orWhere(function ($q2) use ($year, $month) {
                      $q2->whereMonth('incidents.created_at', $month)
                         ->whereYear('incidents.created_at', $year);
                  });
            })
            ->select(
                'users.id',
                'users.name',
                'users.agent_number',
                DB::raw('count(CASE WHEN incidents.id IS NOT NULL THEN 1 END) as total_assigned'),
                DB::raw('count(CASE WHEN incidents.status = \'resolved\' THEN 1 END) as resolved'),
                DB::raw('count(CASE WHEN incidents.status = \'pending\' THEN 1 END) as pending_count'),
                DB::raw('count(CASE WHEN incidents.status = \'investigating\' THEN 1 END) as investigating_count'),
                DB::raw('count(CASE WHEN incidents.priority = \'urgent\' AND incidents.status != \'resolved\' THEN 1 END) as urgent_count')
            )
            ->groupBy('users.id', 'users.name', 'users.agent_number')
            ->orderByDesc('total_assigned')
            ->get();

        $incidentsGeoJson = (clone $baseQuery)
            ->whereNotNull('incidents.latitude')
            ->whereNotNull('incidents.longitude')
            ->with('category:id,name')
            ->get()
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
                    'incident_date' => $i->incident_date?->format('d/m/Y H:i') ?? '',
                ],
            ]);

        $incidentsGeoJsonCollection = ['type' => 'FeatureCollection', 'features' => $incidentsGeoJson->values()->toArray()];

        $station = auth()->user()->policeStation;

        $monthLabels = [
            1 => 'Jan', 2 => 'Fev', 3 => 'Mar', 4 => 'Abr', 5 => 'Mai', 6 => 'Jun',
            7 => 'Jul', 8 => 'Ago', 9 => 'Set', 10 => 'Out', 11 => 'Nov', 12 => 'Dez',
        ];

        return response()->json(compact(
            'total', 'pending', 'investigating', 'resolved', 'archived', 'urgent', 'resolutionRate',
            'byCategory', 'byPriorityFormatted', 'byNeighborhood', 'hourlyData',
            'monthlyTrend', 'agentPerformance', 'incidentsGeoJsonCollection',
            'station', 'year', 'month', 'monthLabels'
        ));
    }

    /**
     * Antes redirecionava para reports.index com year/month; na API devolve
     * diretamente os mesmos dados do relatório para esse período.
     */
    public function generate(): JsonResponse
    {
        return $this->index();
    }

    public function exportPdf()
    {
        $stationId = auth()->user()->police_station_id;
        $year = (int) request('year', now()->year);
        $month = (int) request('month', now()->month);

        $baseQuery = Incident::where('incidents.police_station_id', $stationId)
            ->whereMonth('incidents.created_at', $month)
            ->whereYear('incidents.created_at', $year);

        $total = (clone $baseQuery)->count();
        $resolved = (clone $baseQuery)->where('incidents.status', 'resolved')->count();
        $pending = (clone $baseQuery)->where('incidents.status', 'pending')->count();
        $investigating = (clone $baseQuery)->where('incidents.status', 'investigating')->count();

        $byCategory = (clone $baseQuery)
            ->join('categories', 'incidents.category_id', '=', 'categories.id')
            ->select('categories.name as category', DB::raw('count(*) as count'))
            ->groupBy('categories.name')
            ->orderByDesc('count')
            ->get();

        $station = auth()->user()->policeStation;

        $pdf = Pdf::loadView('post_commander.reports.pdf', [
            'total' => $total,
            'resolved' => $resolved,
            'pending' => $pending,
            'investigating' => $investigating,
            'byCategory' => $byCategory,
            'station' => $station,
            'year' => $year,
            'month' => $month,
        ]);

        $filename = "relatorio_posto_{$station->code}_{$year}_{$month}.pdf";

        return $pdf->download($filename);
    }
}
