<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Exports\GenericExport;
use App\Http\Controllers\Controller;
use App\Models\Incident;
use App\Models\Neighborhood;
use App\Models\User;
use App\Services\SuperAdmin\OperationalDataService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;

/**
 * GET /api/super-admin/export/{type}/{format}
 * type: stations | districts | neighborhoods | users | citizens | incidents
 * format: csv | excel | pdf
 */
class ExportController extends Controller
{
    private const TITLES = [
        'stations' => ['postos-policiais', 'Postos Policiais'],
        'districts' => ['distritos', 'Distritos'],
        'neighborhoods' => ['bairros', 'Bairros'],
        'users' => ['utilizadores-operacionais', 'Utilizadores Operacionais'],
        'citizens' => ['cidadaos', 'Cidadãos'],
        'incidents' => ['ocorrencias', 'Ocorrências'],
    ];

    private const STATUS_LABELS = [
        'pending' => 'Pendente',
        'investigating' => 'Investigação',
        'resolved' => 'Resolvido',
        'archived' => 'Arquivado',
    ];

    private const PRIORITY_LABELS = [
        'urgent' => 'Urgente',
        'high' => 'Alta',
        'medium' => 'Média',
        'low' => 'Baixa',
    ];

    public function __construct(private OperationalDataService $data)
    {
    }

    public function export(Request $request, string $type, string $format): Response
    {
        $data = match ($type) {
            'stations' => $this->stations(),
            'districts' => $this->districts(),
            'neighborhoods' => $this->neighborhoods(),
            'users' => $this->users($request),
            'citizens' => $this->citizens(),
            'incidents' => $this->incidents(),
        };

        [$filename, $title] = self::TITLES[$type];

        return match ($format) {
            'csv' => $this->exportCsv($data, $filename),
            'excel' => Excel::download(new GenericExport($data), "{$filename}.xlsx"),
            'pdf' => $this->exportPdf($data, $filename, $title),
        };
    }

    private function stations(): array
    {
        $headers = ['Nº', 'ID', 'Código', 'Nome', 'Distrito', 'Estado', 'Efetivos', 'Ocorrências', 'Telefone', 'Email'];

        $rows = [];
        foreach ($this->data->stations() as $i => $s) {
            $rows[] = [
                $i + 1,
                $s['id'],
                $s['code'] ?? '',
                $s['name'] ?? '',
                $s['district_name'] ?? '',
                $s['is_active'] ? 'Ativo' : 'Inativo',
                $s['officers_count'] ?? 0,
                $s['incidents_count'] ?? 0,
                $s['phone'] ?? '',
                $s['email'] ?? '',
            ];
        }

        return compact('headers', 'rows');
    }

    private function districts(): array
    {
        $headers = ['Nº', 'ID', 'Nome', 'Província', 'Bairros', 'Postos'];

        $rows = [];
        foreach ($this->data->districts() as $i => $d) {
            $rows[] = [$i + 1, $d['id'], $d['name'] ?? '', $d['province'] ?? '', $d['neighborhoods_count'], $d['police_stations_count']];
        }

        return compact('headers', 'rows');
    }

    private function neighborhoods(): array
    {
        $headers = ['Nº', 'ID', 'Bairro', 'Distrito', 'Latitude', 'Longitude'];

        $rows = Neighborhood::query()
            ->select('id', 'name', 'district_id', 'latitude', 'longitude')
            ->with('district:id,name')
            ->orderBy('name')
            ->get()
            ->values()
            ->map(fn ($n, $i) => [$i + 1, $n->id, $n->name, $n->district?->name ?? '', $n->latitude ?? '', $n->longitude ?? ''])
            ->all();

        return compact('headers', 'rows');
    }

    private function users(Request $request): array
    {
        $headers = ['Nº', 'ID', 'Nome', 'Email', 'Telefone', 'Função', 'Posto', 'ID Posto', 'Distrito', 'ID Distrito', 'Nº Agente', 'Estado', 'Data Criação'];

        $users = $this->data
            ->usersQuery($request->only(['role', 'status', 'district_id', 'police_station_id']))
            ->get()
            ->map(fn (User $u) => $this->data->userRow($u))
            ->reject(fn ($u) => in_array('citizen', $u['roles'], true))
            ->values();

        $rows = $users->map(fn ($u, $i) => [
            $i + 1,
            $u['id'],
            $u['name'],
            $u['email'],
            $u['phone'] ?? '',
            implode(', ', $u['roles']),
            $u['police_station_name'] ?? '',
            $u['police_station_id'] ?? '',
            $u['district_name'] ?? '',
            $u['district_id'] ?? '',
            $u['agent_number'] ?? '',
            $u['status'] === 'active' ? 'Ativo' : 'Inativo',
            $u['created_at']?->format('d/m/Y H:i') ?? '',
        ])->all();

        return compact('headers', 'rows');
    }

    private function citizens(): array
    {
        $headers = ['Nº', 'ID', 'Nome', 'Email', 'Estado'];

        $rows = $this->data->usersQuery(['role' => 'citizen'])
            ->get()
            ->values()
            ->map(fn (User $u, $i) => [$i + 1, $u->id, $u->name, $u->email, $u->status === 'active' ? 'Ativo' : 'Inativo'])
            ->all();

        return compact('headers', 'rows');
    }

    private function incidents(): array
    {
        $headers = ['Nº', 'ID', 'Referência', 'Título', 'Categoria', 'Prioridade', 'Estado', 'Posto', 'Distrito', 'Bairro', 'Latitude', 'Longitude', 'Data', 'Data Resolução'];

        $rows = $this->data->incidentsQuery()
            ->get()
            ->values()
            ->map(function (Incident $incident, $i) {
                $r = $this->data->incidentRow($incident);

                return [
                    $i + 1,
                    $r['id'],
                    $r['reference_code'] ?? '',
                    $r['title'] ?? '',
                    $r['category'] ?? '',
                    self::PRIORITY_LABELS[$r['priority']] ?? ($r['priority'] ?? ''),
                    self::STATUS_LABELS[$r['status']] ?? ($r['status'] ?? ''),
                    $r['police_station_name'] ?? '',
                    $r['district_name'] ?? '',
                    $r['neighborhood_name'] ?? '',
                    $r['latitude'] ?? '',
                    $r['longitude'] ?? '',
                    ($r['incident_date'] ?? $r['created_at'])?->format('d/m/Y H:i') ?? '',
                    $r['resolved_at']?->format('d/m/Y H:i') ?? '',
                ];
            })
            ->all();

        return compact('headers', 'rows');
    }

    private function exportCsv(array $data, string $filename): Response
    {
        return response()->streamDownload(function () use ($data) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF"); // BOM: o Excel abre os acentos corretamente
            fputcsv($handle, $data['headers'], ';');
            foreach ($data['rows'] as $row) {
                fputcsv($handle, $row, ';');
            }
            fclose($handle);
        }, "{$filename}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function exportPdf(array $data, string $filename, string $title): Response
    {
        return Pdf::loadView('super-admin.exports.pdf-table', [
            'title' => $title,
            'headers' => $data['headers'],
            'rows' => $data['rows'],
            'generatedAt' => now()->format('d/m/Y H:i'),
        ])->setPaper('a4', 'landscape')->download("{$filename}.pdf");
    }
}
