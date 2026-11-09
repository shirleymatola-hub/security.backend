<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\District;
use App\Models\Incident;
use App\Models\User;
use App\Services\SuperAdmin\OperationalDataService;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    public function __construct(private OperationalDataService $data)
    {
    }

    /** GET /api/super-admin/reports/generate — Relatório Administrativo em PDF. */
    public function generate(): Response
    {
        $stations = collect($this->data->stations());
        $active = $stations->where('is_active', true)->count();

        $stats = [
            'total_stations' => $stations->count(),
            'active_stations' => $active,
            'inactive_stations' => $stations->count() - $active,
            'total_officers' => $stations->sum('officers_count'),
            'stations_by_district' => $stations
                ->groupBy(fn ($s) => $s['district_name'] ?? 'Desconhecido')
                ->map->count()
                ->all(),
            'total_districts' => District::count(),
            'total_users' => User::count(),
            'roles' => $this->data->roleCounts(),
            'total_incidents' => Incident::count(),
            'incidents_by_status' => $this->countBy('status', 'Desconhecido'),
            'incidents_by_priority' => $this->countBy('priority', 'Desconhecida'),
            'incidents_by_category' => Incident::query()
                ->leftJoin('categories', 'categories.id', '=', 'incidents.category_id')
                ->selectRaw("coalesce(categories.name, 'Desconhecida') as label, count(*) as total")
                ->groupBy('label')
                ->orderByDesc('total')
                ->pluck('total', 'label')
                ->map(fn ($v) => (int) $v)
                ->all(),
        ];

        return Pdf::loadView('super-admin.reports.admin-report', [
            'stats' => $stats,
            'generatedAt' => now()->format('d/m/Y H:i:s'),
        ])->setPaper('a4', 'portrait')->download('relatorio-administrativo-smp.pdf');
    }

    private function countBy(string $column, string $fallback): array
    {
        return Incident::query()
            ->selectRaw("coalesce({$column}, ?) as label, count(*) as total", [$fallback])
            ->groupBy('label')
            ->orderByDesc('total')
            ->pluck('total', 'label')
            ->map(fn ($v) => (int) $v)
            ->all();
    }
}
