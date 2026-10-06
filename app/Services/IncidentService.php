<?php

namespace App\Services;

use App\Models\Incident;
use App\Enums\Priority;
use App\Enums\IncidentStatus;
use Illuminate\Support\Facades\DB;

class IncidentService
{
    public function getStatistics(?int $stationId = null, ?int $year = null, ?int $month = null): array
    {
        $baseQuery = Incident::query();
        if ($stationId) {
            $baseQuery->where('incidents.police_station_id', $stationId);
        }

        $totalQuery = (clone $baseQuery);
        if ($year) {
            $totalQuery->whereYear('incidents.created_at', $year);
        }
        if ($month) {
            $totalQuery->whereMonth('incidents.created_at', $month);
        }

        $total = $totalQuery->count();

        $stationTotalQuery = (clone $baseQuery);
        $stationTotal = $stationTotalQuery->count();

        $withLocation = (clone $totalQuery)
            ->whereNotNull('incidents.latitude')
            ->whereNotNull('incidents.longitude')
            ->count();

        $withoutLocation = $total - $withLocation;

        $byStatus = (clone $totalQuery)
            ->select('incidents.status', DB::raw('count(*) as total'))
            ->groupBy('incidents.status')
            ->pluck('total', 'status')
            ->toArray();

        $byPriority = (clone $totalQuery)
            ->select('incidents.priority', DB::raw('count(*) as total'))
            ->groupBy('incidents.priority')
            ->pluck('total', 'priority')
            ->toArray();

        $byCategory = (clone $totalQuery)
            ->leftJoin('categories', 'incidents.category_id', '=', 'categories.id')
            ->select(DB::raw("COALESCE(categories.name, 'Sem categoria') as category_name"), DB::raw('count(*) as total'))
            ->groupBy('category_name')
            ->pluck('total', 'category_name')
            ->toArray();

        $recentIncidents = (clone $baseQuery)
            ->with(['category', 'reporter', 'assignee'])
            ->latest('incidents.created_at')
            ->limit(10)
            ->get();

        $thisMonth = (clone $baseQuery)
            ->whereMonth('incidents.created_at', now()->month)
            ->whereYear('incidents.created_at', now()->year)
            ->count();

        $lastMonth = (clone $baseQuery)
            ->whereMonth('incidents.created_at', now()->subMonth()->month)
            ->whereYear('incidents.created_at', now()->subMonth()->year)
            ->count();

        $percentageChange = $lastMonth > 0
            ? round((($thisMonth - $lastMonth) / $lastMonth) * 100, 1)
            : 0;

        $pending = $byStatus['pending'] ?? 0;
        $investigating = $byStatus['investigating'] ?? 0;
        $resolved = $byStatus['resolved'] ?? 0;
        $archived = $byStatus['archived'] ?? 0;

        return [
            'total' => $total,
            'station_total' => $stationTotal,
            'with_location' => $withLocation,
            'without_location' => $withoutLocation,
            'by_status' => $byStatus,
            'by_priority' => $byPriority,
            'by_category' => $byCategory,
            'recent_incidents' => $recentIncidents,
            'this_month' => $thisMonth,
            'percentage_change' => $percentageChange,
            'pending' => $pending,
            'investigating' => $investigating,
            'resolved' => $resolved,
            'archived' => $archived,
        ];
    }

    public function getMapData(?int $stationId = null): array
    {
        $query = Incident::whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->select([
                'id',
                'reference_code',
                'title',
                'latitude',
                'longitude',
                'priority',
                'status',
                'category_id',
            ]);

        if ($stationId) {
            $query->where('police_station_id', $stationId);
        }

        return $query->with('category:id,name,slug,color,icon')
            ->get()
            ->toArray();
    }

    public function getHeatmapData(?int $stationId = null): array
    {
        $query = Incident::whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->select(['latitude', 'longitude']);

        if ($stationId) {
            $query->where('police_station_id', $stationId);
        }

        return $query->get()->toArray();
    }
}
