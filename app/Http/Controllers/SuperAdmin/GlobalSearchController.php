<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\District;
use App\Models\Incident;
use App\Models\Neighborhood;
use App\Models\PoliceStation;
use App\Models\User;
use App\Services\SuperAdmin\OperationalDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Pesquisa global do cabeçalho do painel (Ctrl+K). */
class GlobalSearchController extends Controller
{
    private const LIMIT = 5;

    public function __construct(private OperationalDataService $data)
    {
    }

    /** GET /api/super-admin/search?q= */
    public function search(Request $request): JsonResponse
    {
        $query = trim((string) $request->get('q', ''));

        if (mb_strlen($query) < 2) {
            return response()->json(['success' => true, 'results' => []]);
        }

        $like = '%'.addcslashes($query, '\\%_').'%';

        return response()->json([
            'success' => true,
            'results' => [
                'stations' => $this->stations($like),
                'users' => $this->users($like),
                'districts' => $this->districts($like),
                'neighborhoods' => $this->neighborhoods($like),
                'incidents' => $this->incidents($like),
            ],
        ]);
    }

    private function stations(string $like): array
    {
        return PoliceStation::query()
            ->with('district:id,name')
            ->where(fn ($q) => $q->where('name', 'ilike', $like)
                ->orWhere('code', 'ilike', $like)
                ->orWhere('address', 'ilike', $like)
                ->orWhereHas('district', fn ($d) => $d->where('name', 'ilike', $like)))
            ->orderBy('name')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'code' => $s->code,
                'district_name' => $s->district?->name,
                'is_active' => (bool) $s->is_active,
            ])
            ->all();
    }

    private function users(string $like): array
    {
        return $this->data->usersQuery()
            ->where(fn ($q) => $q->where('name', 'ilike', $like)
                ->orWhere('email', 'ilike', $like)
                ->orWhere('phone', 'ilike', $like)
                ->orWhere('agent_number', 'ilike', $like))
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (User $u) => $this->data->userRow($u))
            ->all();
    }

    private function districts(string $like): array
    {
        return District::query()
            ->select('id', 'name', 'province')
            ->where(fn ($q) => $q->where('name', 'ilike', $like)->orWhere('province', 'ilike', $like))
            ->orderBy('name')
            ->limit(self::LIMIT)
            ->get()
            ->toArray();
    }

    private function neighborhoods(string $like): array
    {
        return Neighborhood::query()
            ->select('id', 'name', 'district_id')
            ->with('district:id,name')
            ->where(fn ($q) => $q->where('name', 'ilike', $like)
                ->orWhereHas('district', fn ($d) => $d->where('name', 'ilike', $like)))
            ->orderBy('name')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn ($n) => ['id' => $n->id, 'name' => $n->name, 'district_name' => $n->district?->name])
            ->all();
    }

    private function incidents(string $like): array
    {
        return $this->data->incidentsQuery()
            ->where(fn ($q) => $q->where('incidents.reference_code', 'ilike', $like)
                ->orWhere('incidents.title', 'ilike', $like)
                ->orWhere('incidents.address_detail', 'ilike', $like)
                ->orWhere('incidents.neighborhood', 'ilike', $like))
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (Incident $i) => $this->data->incidentRow($i))
            ->all();
    }
}
