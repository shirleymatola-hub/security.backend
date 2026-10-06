<?php

namespace App\Http\Controllers\Sernic;

use App\Http\Controllers\Controller;
use App\Models\Incident;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;

class ReportController extends Controller
{
    public function index()
    {
        $userId = auth()->id();

        $year = (int) request('year', now()->year);
        $month = (int) request('month', now()->month);

        $query = Incident::where('incidents.assigned_to', $userId)
            ->whereMonth('incidents.created_at', $month)
            ->whereYear('incidents.created_at', $year);

        $total = (clone $query)->count();
        $pending = (clone $query)->where('incidents.status', 'pending')->count();
        $investigating = (clone $query)->where('incidents.status', 'investigating')->count();
        $resolved = (clone $query)->where('incidents.status', 'resolved')->count();
        $archived = (clone $query)->where('incidents.status', 'archived')->count();

        $resolutionPercentage = $total > 0
            ? round((($resolved + $archived) / $total) * 100, 1)
            : 0;

        $byCategory = (clone $query)
            ->join('categories', 'incidents.category_id', '=', 'categories.id')
            ->select('categories.name', DB::raw('count(*) as total'))
            ->groupBy('categories.name')
            ->pluck('total', 'name')
            ->toArray();

        $byMonth = Incident::where('incidents.assigned_to', $userId)
            ->whereYear('incidents.created_at', $year)
            ->selectRaw("EXTRACT(MONTH FROM incidents.created_at)::int as month_number, count(*) as total")
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

        $recentIncidents = Incident::where('incidents.assigned_to', $userId)
            ->whereMonth('incidents.created_at', $month)
            ->whereYear('incidents.created_at', $year)
            ->with('category:id,name')
            ->latest()
            ->get();

        return response()->json([
            'month' => $month,
            'year' => $year,
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

    public function exportPdf()
    {
        $userId = auth()->id();
        $year = (int) request('year', now()->year);
        $month = (int) request('month', now()->month);

        $query = Incident::where('incidents.assigned_to', $userId)
            ->whereMonth('incidents.created_at', $month)
            ->whereYear('incidents.created_at', $year);

        $total = (clone $query)->count();
        $resolved = (clone $query)->clone()->where('incidents.status', 'resolved')->count();
        $pending = (clone $query)->clone()->where('incidents.status', 'pending')->count();
        $investigating = (clone $query)->clone()->where('incidents.status', 'investigating')->count();
        $archived = (clone $query)->clone()->where('incidents.status', 'archived')->count();

        $byCategory = (clone $query)
            ->join('categories', 'incidents.category_id', '=', 'categories.id')
            ->select('categories.name as category', DB::raw('count(*) as count'))
            ->groupBy('categories.name')
            ->orderByDesc('count')
            ->get();

        $byPriority = collect();
        foreach (['urgent', 'high', 'medium', 'low'] as $priority) {
            $count = (clone $query)->where('incidents.priority', $priority)->count();
            $byPriority->push([
                'priority' => $priority,
                'count' => $count,
                'label' => match($priority) {
                    'urgent' => 'Urgente',
                    'high' => 'Alta',
                    'medium' => 'Média',
                    'low' => 'Baixa',
                },
            ]);
        }

        $incidentsList = (clone $query)->with('category:id,name')->latest()->limit(50)->get();

        $monthName = now()->setMonth($month)->format('F');

        $report = [
            'year' => $year,
            'month' => $month,
            'month_name' => $monthName,
            'total' => $total,
            'resolved' => $resolved,
            'pending' => $pending,
            'investigating' => $investigating,
            'archived' => $archived,
            'resolution_rate' => $total > 0 ? round(($resolved / $total) * 100, 1) : 0,
            'by_category' => $byCategory,
            'by_priority' => $byPriority,
            'incidents' => $incidentsList,
        ];

        $user = auth()->user();

        $pdf = Pdf::loadView('sernic.reports.pdf', [
            'report' => $report,
            'user' => $user,
            'year' => $year,
            'month' => $month,
        ]);

        $filename = "relatorio_sernic_{$year}_{$month}.pdf";

        return $pdf->download($filename);
    }
}
