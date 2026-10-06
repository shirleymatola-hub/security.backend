<?php

namespace App\Services;

use App\Models\Incident;
use App\Models\Category;
use App\Models\PoliceStation;
use Illuminate\Support\Facades\DB;

class ReportService
{
    public function generateMonthlyReport(int $year, int $month, ?int $stationId = null): array
    {
        $query = Incident::whereMonth('incidents.created_at', $month)
            ->whereYear('incidents.created_at', $year);

        if ($stationId) {
            $query->where('incidents.police_station_id', $stationId);
        }

        $incidents = $query->get();

        $total = $incidents->count();
        $resolved = $incidents->where('status', 'resolved')->count();
        $pending = $incidents->where('status', 'pending')->count();
        $investigating = $incidents->where('status', 'investigating')->count();
        $archived = $incidents->where('status', 'archived')->count();

        $byCategory = $incidents->groupBy('category_id')->map(function ($group) {
            return [
                'category' => $group->first()->category->name ?? 'Desconhecida',
                'count' => $group->count(),
            ];
        })->values()->sortByDesc('count')->values();

        $byPriority = collect();
        foreach (['urgent', 'high', 'medium', 'low'] as $priority) {
            $count = $incidents->where('priority', $priority)->count();
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

        $byNeighborhood = $incidents->where('neighborhood')
            ->groupBy('neighborhood')
            ->map(fn($group) => ['neighborhood' => $group->first()->neighborhood, 'count' => $group->count()])
            ->values()->sortByDesc('count')->values();

        $dailyDistribution = $incidents->groupBy(fn($i) => $i->created_at->day)
            ->map(fn($group) => $group->count())
            ->toArray();

        $incidentsList = $query->clone()->with('category:id,name')->latest('incidents.created_at')->limit(50)->get();

        $monthLabels = [
            1 => 'Jan', 2 => 'Fev', 3 => 'Mar', 4 => 'Abr', 5 => 'Mai', 6 => 'Jun',
            7 => 'Jul', 8 => 'Ago', 9 => 'Set', 10 => 'Out', 11 => 'Nov', 12 => 'Dez',
        ];

        $monthlyTrendQuery = Incident::whereYear('incidents.created_at', $year);
        if ($stationId) {
            $monthlyTrendQuery->where('incidents.police_station_id', $stationId);
        }
        $monthlyTrendData = $monthlyTrendQuery
            ->select(
                DB::raw('EXTRACT(MONTH FROM incidents.created_at)::int as month'),
                DB::raw('count(*) as total')
            )
            ->groupBy(DB::raw('EXTRACT(MONTH FROM incidents.created_at)'))
            ->pluck('total', 'month')
            ->toArray();

        $monthlyTrend = collect(range(1, 12))->map(fn($m) => [
            'month' => $monthLabels[$m],
            'total' => $monthlyTrendData[$m] ?? 0,
        ])->toArray();

        $categoryTrendQuery = Incident::whereYear('incidents.created_at', $year);
        if ($stationId) {
            $categoryTrendQuery->where('incidents.police_station_id', $stationId);
        }
        $categoryTrend = $categoryTrendQuery
            ->leftJoin('categories', 'incidents.category_id', '=', 'categories.id')
            ->select(DB::raw("COALESCE(categories.name, 'Sem categoria') as name"), DB::raw('count(*) as total'))
            ->groupBy('name')
            ->pluck('total', 'name')
            ->toArray();

        return [
            'year' => $year,
            'month' => $month,
            'month_name' => now()->setMonth($month)->format('F'),
            'total' => $total,
            'resolved' => $resolved,
            'pending' => $pending,
            'investigating' => $investigating,
            'archived' => $archived,
            'resolution_rate' => $total > 0 ? round(($resolved / $total) * 100, 1) : 0,
            'by_category' => $byCategory,
            'by_priority' => $byPriority,
            'by_neighborhood' => $byNeighborhood,
            'daily_distribution' => $dailyDistribution,
            'incidents' => $incidentsList,
            'monthly_trend' => $monthlyTrend,
            'category_trend' => $categoryTrend,
        ];
    }

    public function getMonthlyTrend(int $year, ?int $stationId = null): array
    {
        $query = Incident::whereYear('incidents.created_at', $year);

        if ($stationId) {
            $query->where('incidents.police_station_id', $stationId);
        }

        $monthlyData = $query->select(
            DB::raw('EXTRACT(MONTH FROM incidents.created_at) as month'),
            DB::raw('count(*) as total')
        )
        ->groupBy(DB::raw('EXTRACT(MONTH FROM incidents.created_at)'))
        ->pluck('total', 'month')
        ->toArray();

        $result = [];
        for ($i = 1; $i <= 12; $i++) {
            $result[$i] = $monthlyData[$i] ?? 0;
        }

        return $result;
    }

    public function getStationComparison(int $year): array
    {
        return PoliceStation::withCount(['incidents' => function ($query) use ($year) {
            $query->whereYear('created_at', $year);
        }])
        ->where('is_active', true)
        ->orderByDesc('incidents_count')
        ->get()
        ->toArray();
    }

    public function getYearlyOverview(int $year): array
    {
        $incidents = Incident::whereYear('created_at', $year)->get();

        return [
            'year' => $year,
            'total' => $incidents->count(),
            'by_month' => $incidents->groupBy(fn($i) => $i->created_at->month)
                ->map(fn($group) => $group->count())
                ->toArray(),
            'by_status' => $incidents->groupBy('status')
                ->map(fn($group) => $group->count())
                ->toArray(),
            'by_priority' => $incidents->groupBy('priority')
                ->map(fn($group) => $group->count())
                ->toArray(),
        ];
    }

    public function generateAllTimeReport(?int $stationId = null): array
    {
        $query = Incident::query();

        if ($stationId) {
            $query->where('incidents.police_station_id', $stationId);
        }

        $incidents = $query->get();

        $total = $incidents->count();
        $resolved = $incidents->where('status', 'resolved')->count();
        $pending = $incidents->where('status', 'pending')->count();
        $investigating = $incidents->where('status', 'investigating')->count();
        $archived = $incidents->where('status', 'archived')->count();

        $byCategory = $incidents->groupBy('category_id')->map(function ($group) {
            return [
                'category' => $group->first()->category->name ?? 'Sem categoria',
                'count' => $group->count(),
            ];
        })->values()->sortByDesc('count')->values();

        $byPriority = collect();
        foreach (['urgent', 'high', 'medium', 'low'] as $priority) {
            $count = $incidents->where('priority', $priority)->count();
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

        $byNeighborhood = $incidents->where('neighborhood')
            ->groupBy('neighborhood')
            ->map(fn($group) => ['neighborhood' => $group->first()->neighborhood, 'count' => $group->count()])
            ->values()->sortByDesc('count')->values();

        $monthlyTrendQuery = Incident::query();
        if ($stationId) {
            $monthlyTrendQuery->where('incidents.police_station_id', $stationId);
        }
        $monthlyTrendData = $monthlyTrendQuery
            ->select(
                DB::raw('EXTRACT(MONTH FROM incidents.created_at)::int as month'),
                DB::raw('count(*) as total')
            )
            ->groupBy(DB::raw('EXTRACT(MONTH FROM incidents.created_at)'))
            ->pluck('total', 'month')
            ->toArray();

        $monthLabels = [
            1 => 'Jan', 2 => 'Fev', 3 => 'Mar', 4 => 'Abr', 5 => 'Mai', 6 => 'Jun',
            7 => 'Jul', 8 => 'Ago', 9 => 'Set', 10 => 'Out', 11 => 'Nov', 12 => 'Dez',
        ];

        $monthlyTrend = collect(range(1, 12))->map(fn($m) => [
            'month' => $monthLabels[$m],
            'total' => $monthlyTrendData[$m] ?? 0,
        ])->toArray();

        $categoryTrendQuery = Incident::query();
        if ($stationId) {
            $categoryTrendQuery->where('incidents.police_station_id', $stationId);
        }
        $categoryTrend = $categoryTrendQuery
            ->leftJoin('categories', 'incidents.category_id', '=', 'categories.id')
            ->select(DB::raw("COALESCE(categories.name, 'Sem categoria') as name"), DB::raw('count(*) as total'))
            ->groupBy('name')
            ->pluck('total', 'name')
            ->toArray();

        $incidentsList = $query->clone()->with('category:id,name')->latest('incidents.created_at')->limit(50)->get();

        $totalWithLocation = $incidents->filter(fn($i) => $i->latitude !== null && $i->longitude !== null)->count();
        $totalWithoutLocation = $total - $totalWithLocation;

        return [
            'year' => null,
            'month' => null,
            'month_name' => 'Histórico',
            'total' => $total,
            'resolved' => $resolved,
            'pending' => $pending,
            'investigating' => $investigating,
            'archived' => $archived,
            'resolution_rate' => $total > 0 ? round(($resolved / $total) * 100, 1) : 0,
            'by_category' => $byCategory,
            'by_priority' => $byPriority,
            'by_neighborhood' => $byNeighborhood,
            'daily_distribution' => [],
            'incidents' => $incidentsList,
            'monthly_trend' => $monthlyTrend,
            'category_trend' => $categoryTrend,
            'total_with_location' => $totalWithLocation,
            'total_without_location' => $totalWithoutLocation,
        ];
    }
}
