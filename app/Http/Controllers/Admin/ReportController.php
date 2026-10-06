<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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
     * GET /api/admin/reports?year=&month= — dados de admin/reports/index.blade.php.
     */
    public function index(): JsonResponse
    {
        $year = (int) request('year', now()->year);
        $month = (int) request('month', now()->month);

        $report = $this->reportService->generateMonthlyReport($year, $month);
        $trend = $this->reportService->getMonthlyTrend($year);
        $stationComparison = $this->reportService->getStationComparison($year);
        $yearlyOverview = $this->reportService->getYearlyOverview($year);

        return response()->json([
            'report' => $report,
            'trend' => $trend,
            'stationComparison' => $stationComparison,
            'yearlyOverview' => $yearlyOverview,
            'year' => $year,
            'month' => $month,
            // Listas dos <select> do filtro (calculadas na view original)
            'years' => range(now()->year, now()->year - 5),
        ]);
    }

    /**
     * POST /api/admin/reports/generate — valida o período (antes redirecionava
     * para o index com estes parâmetros; o frontend navega com eles).
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

        $report = $this->reportService->generateMonthlyReport($year, $month);
        $trend = $this->reportService->getMonthlyTrend($year);

        $pdf = Pdf::loadView('admin.reports.pdf', [
            'report' => $report,
            'trend' => $trend,
            'year' => $year,
            'month' => $month,
        ]);

        $filename = "relatorio_smp_{$year}_{$month}.pdf";

        return $pdf->download($filename);
    }

    public function exportCsv()
    {
        $year = (int) request('year', now()->year);
        $month = (int) request('month', now()->month);

        $incidents = \App\Models\Incident::whereMonth('created_at', $month)
            ->whereYear('created_at', $year)
            ->with(['category', 'reporter', 'policeStation'])
            ->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"ocorrencias_{$year}_{$month}.csv\"",
        ];

        $callback = function () use ($incidents) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Referência', 'Título', 'Categoria', 'Prioridade', 'Estado', 'Data', 'Localização', 'Bairro', 'Posto']);

            foreach ($incidents as $incident) {
                fputcsv($file, [
                    $incident->reference_code,
                    $incident->title,
                    $incident->category->name ?? '',
                    $incident->priority,
                    $incident->status,
                    $incident->incident_date?->format('d/m/Y H:i'),
                    $incident->address_detail,
                    $incident->neighborhood,
                    $incident->policeStation->name ?? '',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
