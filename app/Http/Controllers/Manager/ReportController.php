<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

class ReportController extends Controller
{
    public function __construct(
        protected ReportService $reportService
    ) {}

    /**
     * GET /api/manager/reports?year=&month= — month vazio = histórico (todas as ocorrências).
     */
    public function index(): JsonResponse
    {
        $stationId = auth()->user()->police_station_id;
        $year = (int) request('year', now()->year);
        $month = request('month', '');
        $isAllTime = ($month === '' || $month === null || $month === '0' || $month === 0);

        if ($isAllTime) {
            $report = $this->reportService->generateAllTimeReport($stationId);
            $trend = $this->reportService->getMonthlyTrend(now()->year, $stationId);
        } else {
            $month = (int) $month;
            $report = $this->reportService->generateMonthlyReport($year, $month, $stationId);
            $trend = $this->reportService->getMonthlyTrend($year, $stationId);
        }

        $years = range(now()->year - 5, now()->year);

        return response()->json([
            'report' => $report,
            'trend' => $trend,
            'year' => $year,
            'month' => $isAllTime ? '' : $month,
            'isAllTime' => $isAllTime,
            'years' => $years,
        ]);
    }

    /**
     * POST /api/manager/reports/generate — valida o período (antes redirecionava
     * para o index com estes parâmetros; o frontend navega com eles).
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
        $stationId = auth()->user()->police_station_id;
        $year = (int) request('year', now()->year);
        $month = request('month', '');
        $isAllTime = ($month === '' || $month === null || $month === '0' || $month === 0);

        if ($isAllTime) {
            $report = $this->reportService->generateAllTimeReport($stationId);
        } else {
            $month = (int) $month;
            $report = $this->reportService->generateMonthlyReport($year, $month, $stationId);
        }

        $station = auth()->user()->policeStation;

        $pdf = Pdf::loadView('manager.reports.pdf', [
            'report' => $report,
            'station' => $station,
            'year' => $year,
            'month' => $month,
            'isAllTime' => $isAllTime,
        ]);

        $filename = $isAllTime
            ? "relatorio_posto_{$station->code}_historico.pdf"
            : "relatorio_posto_{$station->code}_{$year}_{$month}.pdf";

        return $pdf->download($filename);
    }

    public function exportServiceOrder()
    {
        $station = auth()->user()->policeStation()->with('commander')->first();
        $authUser = auth()->user();

        $officersCount = User::role('police')
            ->where('police_station_id', $authUser->police_station_id)
            ->count();

        $pdf = Pdf::loadView('manager.reports.service-order', [
            'station' => $station,
            'authUser' => $authUser,
            'officersCount' => $officersCount,
        ]);

        $filename = "ordem_servico_{$station->code}_" . now()->format('Ymd') . ".pdf";

        return $pdf->download($filename);
    }
}
