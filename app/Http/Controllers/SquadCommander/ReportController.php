<?php

namespace App\Http\Controllers\SquadCommander;

use App\Http\Controllers\Controller;
use App\Models\PoliceStation;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

class ReportController extends Controller
{
    public function __construct(
        protected ReportService $reportService
    ) {}

    private function getCommanderStationId(): int
    {
        $stationId = auth()->user()->police_station_id;
        if (!$stationId) {
            abort(403, 'Nenhuma esquadra associada ao seu utilizador.');
        }
        PoliceStation::findOrFail($stationId);
        return $stationId;
    }

    public function index(): JsonResponse
    {
        $year = (int) request('year', now()->year);
        $month = (int) request('month', now()->month);
        $stationId = $this->getCommanderStationId();

        $report = $this->reportService->generateMonthlyReport($year, $month, $stationId);
        $trend = $this->reportService->getMonthlyTrend($year, $stationId);

        return response()->json([
            'report' => $report,
            'trend' => array_values($trend),
            'year' => $year,
            'month' => $month,
            // Opções do seletor de ano (antes calculadas na view: range(now()->year, now()->year - 3)).
            'years' => range(now()->year, now()->year - 3),
        ]);
    }

    /**
     * Antes redirecionava para reports.index com os filtros validados;
     * agora devolve os filtros (o frontend navega para /squad-commander/reports).
     */
    public function generate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'year' => 'required|integer|min:2020|max:' . (now()->year + 1),
            'month' => 'required|integer|min:1|max:12',
        ]);

        return response()->json($validated);
    }

    public function exportPdf()
    {
        $year = (int) request('year', now()->year);
        $month = (int) request('month', now()->month);
        $stationId = $this->getCommanderStationId();

        $report = $this->reportService->generateMonthlyReport($year, $month, $stationId);

        $pdf = Pdf::loadView('squad_commander.reports.pdf', [
            'report' => $report,
            'year' => $year,
            'month' => $month,
            'station' => PoliceStation::find($stationId),
        ]);

        $filename = "relatorio_esquadra_{$year}_{$month}.pdf";

        return $pdf->download($filename);
    }

    public function stationComparison()
    {
        abort(404, 'Funcionalidade não disponível para esta conta.');
    }
}
