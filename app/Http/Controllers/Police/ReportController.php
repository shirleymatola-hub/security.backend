<?php

namespace App\Http\Controllers\Police;

use App\Http\Controllers\Controller;
use App\Services\IncidentService;
use App\Services\ReportService;
use App\Models\Incident;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Barryvdh\DomPDF\Facade\Pdf;

class ReportController extends Controller
{
    public function __construct(
        private IncidentService $incidentService,
        private ReportService $reportService,
    ) {
    }

    public function exportPdf()
    {
        $user = auth()->user();
        $stationId = $user->police_station_id;
        $userId = $user->id;
        $year = (int) request('year', now()->year);
        $month = (int) request('month', now()->month);

        $report = $this->reportService->generateMonthlyReport($year, $month, $stationId);
        $station = $user->policeStation;

        $pdf = Pdf::loadView('police.reports.pdf', [
            'report' => $report,
            'station' => $station,
            'year' => $year,
            'month' => $month,
        ]);

        $filename = "relatorio_posto_{$station->code}_{$year}_{$month}.pdf";

        return $pdf->download($filename);
    }

    public function exportIncidentPdf($incidentId)
    {
        $incident = \App\Models\Incident::with(['category', 'reporter', 'assignee', 'policeStation'])
            ->findOrFail($incidentId);

        // Só exporta ocorrências que o utilizador pode ver (no original faltava esta verificação).
        Gate::authorize('view', $incident);

        // A view police.incidents.pdf não existia no projeto original (criada em resources/views/police/incidents/pdf.blade.php)
        $pdf = Pdf::loadView('police.incidents.pdf', [
            'incident' => $incident,
        ]);

        $filename = "ocorrencia_{$incident->reference_code}.pdf";

        return $pdf->download($filename);
    }

    public function index(): JsonResponse
    {
        $user = auth()->user();
        $stationId = $user?->police_station_id;
        $userId = $user?->id;

        $stats = $this->incidentService->getStatistics($stationId);

        $total = $stats['total'];
        $byStatus = $stats['by_status'];

        $pending = $byStatus['pending'] ?? 0;
        $investigating = $byStatus['investigating'] ?? 0;
        $resolved = $byStatus['resolved'] ?? 0;
        $archived = $byStatus['archived'] ?? 0;

        $resolutionPercentage = $total > 0
            ? round((($resolved + $archived) / $total) * 100, 1)
            : 0;

        $byCategory = $stats['by_category'];

        $year = (int) request('year', now()->year);
        $month = (int) request('month', now()->month);
        $years = range(now()->year - 5, now()->year);

        $byMonth = DB::table('incidents')
            ->selectRaw("EXTRACT(MONTH FROM created_at)::int as month_number, count(*) as total")
            ->whereYear('created_at', $year)
            ->when($stationId, fn($q) => $q->where('police_station_id', $stationId))
            ->groupBy('month_number')
            ->pluck('total', 'month_number')
            ->mapWithKeys(fn($v, $k) => [sprintf('%02d', $k) => $v])
            ->toArray();

        $monthNames = [
            '01' => 'Janeiro',
            '02' => 'Fevereiro',
            '03' => 'Março',
            '04' => 'Abril',
            '05' => 'Maio',
            '06' => 'Junho',
            '07' => 'Julho',
            '08' => 'Agosto',
            '09' => 'Setembro',
            '10' => 'Outubro',
            '11' => 'Novembro',
            '12' => 'Dezembro',
        ];

        $incidentsByMonth = collect($monthNames)->map(function ($name, $num) use ($byMonth) {
            return [
                'month' => $name,
                'total' => $byMonth[$num] ?? 0,
            ];
        })->values()->toArray();

        $recentIncidents = Incident::whereMonth('created_at', $month)
            ->whereYear('created_at', $year)
            ->when($stationId, fn($q) => $q->where('police_station_id', $stationId))
            ->with('category:id,name')
            ->latest()
            ->get();

        return response()->json([
            'month' => $month,
            'year' => $year,
            'years' => $years,
            'totalIncidents' => $total,
            'pending' => $pending,
            'investigating' => $investigating,
            'resolved' => $resolved,
            'archived' => $archived,
            'resolutionPercentage' => $resolutionPercentage,
            'incidentsByCategory' => $byCategory,
            'incidentsByMonth' => $incidentsByMonth,
            'recentIncidents' => $recentIncidents,
        ]);
    }
}