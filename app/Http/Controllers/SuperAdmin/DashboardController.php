<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\District;
use App\Models\Incident;
use App\Models\User;
use App\Services\AreaDeEstudoService;
use App\Services\SuperAdmin\OperationalDataService;
use App\Services\SuperAdmin\PoliceInfrastructureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    public function __construct(
        private OperationalDataService $data,
        private AreaDeEstudoService $areaDeEstudo,
        private PoliceInfrastructureService $infrastructure,
    ) {
    }

    /** GET /api/super-admin/dashboard */
    public function index(): JsonResponse
    {
        $stats = [
            'stations' => null,
            'stations_active' => null,
            'stations_inactive' => null,
            'police_units' => null,
            'police_units_by_type' => [],
            'districts' => null,
            'neighborhoods' => null,
            'users' => null,
            'api_available' => true,
        ];

        $charts = [
            'stations_per_district' => [],
            'officers_per_role' => [],
            'users_per_role' => [],
            'stations_active_inactive' => ['active' => 0, 'inactive' => 0],
            'neighborhoods_per_district' => [],
            'incidents_trend' => ['labels' => [], 'data' => []],
        ];

        try {
            $stations = collect($this->data->stations());
            $active = $stations->where('is_active', true)->count();

            $infraestruturas = $this->infrastructure->features(true);
            $metadata = $infraestruturas['metadata'] ?? [];

            $stats['stations'] = $stations->count();
            $stats['stations_active'] = $active;
            $stats['stations_inactive'] = $stations->count() - $active;
            $stats['police_units'] = (int) ($metadata['total'] ?? count($infraestruturas['features']));
            $stats['police_units_by_type'] = [
                'esquadras' => (int) ($metadata['esquadras'] ?? 0),
                'postos' => (int) ($metadata['postos'] ?? 0),
                'sernic' => (int) ($metadata['sernic'] ?? 0),
            ];
            $stats['districts'] = District::count();
            $stats['neighborhoods'] = $this->areaDeEstudo->distinctBairroCount();
            $stats['users'] = User::count();

            $charts['stations_per_district'] = $stations
                ->groupBy(fn ($s) => $s['district_name'] ?? 'Sem distrito')
                ->map(fn ($group) => $group->count())
                ->sortDesc()
                ->all();

            $charts['stations_active_inactive'] = [
                'active' => $active,
                'inactive' => $stations->count() - $active,
            ];

            $charts['neighborhoods_per_district'] = District::withCount('neighborhoods')
                ->orderBy('name')
                ->pluck('neighborhoods_count', 'name')
                ->all();

            $charts['users_per_role'] = $this->data->roleCounts();
            $charts['officers_per_role'] = $this->data->roleCounts(onlyOfficers: true);

            $monthly = Incident::query()
                ->select(DB::raw("to_char(created_at, 'YYYY-MM') as ym"), DB::raw('count(*) as total'))
                ->whereNotNull('created_at')
                ->groupBy('ym')
                ->orderBy('ym')
                ->pluck('total', 'ym')
                ->map(fn ($v) => (int) $v)
                ->slice(-24);

            $charts['incidents_trend'] = [
                'labels' => $monthly->keys()->values()->all(),
                'data' => $monthly->values()->all(),
            ];
        } catch (\Throwable $e) {
            Log::error('Super Admin: erro ao carregar o dashboard', ['error' => $e->getMessage()]);
            $stats['api_available'] = false;
        }

        return response()->json(compact('stats', 'charts'));
    }
}
