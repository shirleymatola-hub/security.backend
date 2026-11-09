<?php

namespace App\Services\SuperAdmin;

use App\Models\District;
use App\Models\Incident;
use App\Models\PoliceStation;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Dados operacionais do SMP para o painel Super Admin.
 *
 * Antes o super-admin era um projeto separado e obtinha estes dados por HTTP
 * (PoliceApiService → /api/internal/*). Agora faz parte desta API e lê os
 * modelos diretamente, na mesma base de dados.
 */
class OperationalDataService
{
    /** Perfis do SMP (os mesmos nomes do spatie/laravel-permission). */
    public const ROLES = [
        'admin' => 'Administrador',
        'manager' => 'Gestor',
        'police' => 'Polícia',
        'post_commander' => 'Comandante de Posto',
        'squad_commander' => 'Comandante de Esquadra',
        'district_commander' => 'Comandante de Distrito',
        'sernic_officer' => 'Oficial SERNIC',
        'citizen' => 'Cidadão',
    ];

    /** Perfis que podem ser convidados pelo Super Admin. */
    public const INVITABLE_ROLES = [
        'police' => 'Polícia',
        'post_commander' => 'Comandante de Posto',
        'squad_commander' => 'Comandante de Esquadra',
        'district_commander' => 'Comandante de Distrito',
        'manager' => 'Gestor',
        'sernic_officer' => 'Oficial SERNIC',
    ];

    public const USER_STATUSES = [
        'active' => 'Ativo',
        'inactive' => 'Inativo',
    ];

    private ?array $districtNames = null;

    /** Todos os postos policiais (ativos e inativos), ordenados por id. */
    public function stations(): array
    {
        return PoliceStation::query()
            ->select('id', 'name', 'code', 'address', 'phone', 'email', 'latitude', 'longitude', 'district_id', 'commander_id', 'is_active')
            ->with(['district:id,name', 'commander:id,name'])
            ->withCount(['officers', 'incidents'])
            ->orderBy('id')
            ->get()
            ->map(fn (PoliceStation $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'code' => $s->code,
                'address' => $s->address,
                'phone' => $s->phone,
                'email' => $s->email,
                'latitude' => $s->latitude !== null ? (float) $s->latitude : null,
                'longitude' => $s->longitude !== null ? (float) $s->longitude : null,
                'district_id' => $s->district_id,
                'district_name' => $s->district?->name,
                'commander_id' => $s->commander_id,
                'commander' => $s->commander ? ['id' => $s->commander->id, 'name' => $s->commander->name] : null,
                'is_active' => (bool) $s->is_active,
                'officers_count' => $s->officers_count,
                'incidents_count' => $s->incidents_count,
            ])
            ->all();
    }

    /** Todos os distritos com contagens, ordenados por id. */
    public function districts(): array
    {
        return District::query()
            ->select('id', 'name', 'province', 'latitude', 'longitude')
            ->withCount(['neighborhoods', 'policeStations'])
            ->orderBy('id')
            ->get()
            ->map(fn (District $d) => [
                'id' => $d->id,
                'name' => $d->name,
                'province' => $d->province,
                'latitude' => $d->latitude !== null ? (float) $d->latitude : null,
                'longitude' => $d->longitude !== null ? (float) $d->longitude : null,
                'neighborhoods_count' => $d->neighborhoods_count,
                'police_stations_count' => $d->police_stations_count,
            ])
            ->all();
    }

    /** Lista simples [{id, name}] para filtros e formulários. */
    public function districtOptions(): array
    {
        return District::query()->select('id', 'name')->orderBy('name')->get()->toArray();
    }

    public function stationOptions(): array
    {
        return PoliceStation::query()
            ->select('id', 'name', 'district_id')
            ->with('district:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (PoliceStation $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'district_id' => $s->district_id,
                'district_name' => $s->district?->name,
            ])
            ->all();
    }

    public function districtName(?int $id): ?string
    {
        if (! $id) {
            return null;
        }

        $this->districtNames ??= District::query()->pluck('name', 'id')->all();

        return $this->districtNames[$id] ?? null;
    }

    /**
     * Consulta de utilizadores com os filtros do painel
     * (role, police_station_id, district_id, status).
     */
    public function usersQuery(array $filters = []): Builder
    {
        $query = User::query()
            ->select('id', 'name', 'email', 'phone', 'status', 'police_station_id', 'district_id', 'agent_number', 'created_at')
            ->with(['policeStation:id,name,district_id', 'roles:id,name'])
            ->orderBy('id');

        $role = $filters['role'] ?? null;
        if ($role && array_key_exists($role, self::ROLES)) {
            $query->whereHas('roles', fn ($q) => $q->where('name', $role));
        }

        if (! empty($filters['police_station_id'])) {
            $query->where('police_station_id', (int) $filters['police_station_id']);
        }

        if (! empty($filters['district_id'])) {
            $query->where('district_id', (int) $filters['district_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query;
    }

    public function userRow(User $user): array
    {
        $districtId = $user->district_id ?? $user->policeStation?->district_id;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'status' => $user->status,
            'police_station_id' => $user->police_station_id,
            'police_station_name' => $user->policeStation?->name,
            'district_id' => $user->district_id,
            'district_name' => $this->districtName($districtId),
            'agent_number' => $user->agent_number,
            'roles' => $user->roles->pluck('name')->values()->all(),
            'created_at' => $user->created_at,
        ];
    }

    /** Consulta de ocorrências com as relações usadas nas listagens/exportações. */
    public function incidentsQuery(): Builder
    {
        return Incident::query()
            ->with([
                'category:id,name,color',
                'policeStation:id,name,district_id',
                'neighborhoodRel:id,name',
            ])
            ->orderByDesc('created_at');
    }

    public function incidentRow(Incident $incident): array
    {
        return [
            'id' => $incident->id,
            'reference' => $incident->reference_code,
            'reference_code' => $incident->reference_code,
            'title' => $incident->title,
            'category' => $incident->category?->name,
            'category_color' => $incident->category?->color,
            'priority' => $incident->priority,
            'status' => $incident->status,
            'police_station_id' => $incident->police_station_id,
            'police_station_name' => $incident->policeStation?->name,
            'district_name' => $this->districtName($incident->policeStation?->district_id),
            'neighborhood_name' => $incident->neighborhoodRel?->name ?? $incident->neighborhood,
            'address' => $incident->address_detail,
            'latitude' => $incident->latitude !== null ? (float) $incident->latitude : null,
            'longitude' => $incident->longitude !== null ? (float) $incident->longitude : null,
            'incident_date' => $incident->incident_date,
            'resolved_at' => $incident->resolved_at,
            'created_at' => $incident->created_at,
        ];
    }

    /**
     * Número de utilizadores por perfil (role => total), por ordem decrescente.
     * Com $onlyOfficers conta só utilizadores associados a um posto.
     */
    public function roleCounts(bool $onlyOfficers = false): array
    {
        $tables = config('permission.table_names');

        $query = DB::table($tables['model_has_roles'].' as mhr')
            ->join($tables['roles'].' as r', 'r.id', '=', 'mhr.role_id')
            ->where('mhr.model_type', (new User)->getMorphClass())
            ->select('r.name', DB::raw('count(*) as total'))
            ->groupBy('r.name')
            ->orderByDesc('total');

        if ($onlyOfficers) {
            $query->join('users as u', 'u.id', '=', 'mhr.model_id')->whereNotNull('u.police_station_id');
        }

        return $query->pluck('total', 'name')->map(fn ($v) => (int) $v)->all();
    }
}
