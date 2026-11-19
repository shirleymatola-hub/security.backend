<?php

namespace App\Http\Controllers\Citizen;

use App\Http\Controllers\Controller;
use App\Models\Incident;
use App\Models\Category;
use App\Models\Neighborhood;
use App\Services\AuditService;
use App\Services\IncidentLocationService;
use App\Services\NotificationService;
use App\Http\Requests\StoreIncidentRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IncidentController extends Controller
{
    public function __construct(private readonly IncidentLocationService $locationService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $query = Incident::where('is_public', true)
            ->with('category');

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('incident_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('incident_date', '<=', $request->date_to);
        }

        $incidents = $query->latest('incident_date')->paginate(15);

        // Ocorrências públicas de outros cidadãos: não expor quem denunciou.
        $userId = (int) auth()->id();
        $incidents->getCollection()->each(function (Incident $incident) use ($userId) {
            $incident->setAttribute('is_mine', (int) $incident->reported_by === $userId);
            $incident->makeHidden(['reported_by', 'assigned_to']);
        });

        return response()->json([
            'incidents' => $incidents,
            'categories' => Category::where('is_active', true)->get(),
        ]);
    }

    public function create(): JsonResponse
    {
        // O formulário só usa id e nome (sem a geometria dos bairros).
        return response()->json([
            'neighborhoods' => Neighborhood::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreIncidentRequest $request): JsonResponse
    {
        $location = $this->locationService->resolve($request->latitude, $request->longitude);

        $description = $request->description;
        if ($request->hasFile('audio_file')) {
            $audioPath = $request->file('audio_file')->store('incidents/audio', 'public');
            $description = $description ? $description . ' [Audio: ' . $audioPath . ']' : '[Audio: ' . $audioPath . ']';
        }

        $referenceCode = Incident::generateReferenceCode();
        $reportedBy = auth()->id();
        $isAnonymous = $request->boolean('is_anonymous');

        $incident = DB::transaction(function () use (
            $referenceCode,
            $description,
            $location,
            $request,
            $reportedBy,
            $isAnonymous
        ): Incident {
            $incident = Incident::create([
                'reference_code' => $referenceCode,
                'title' => 'Ocorrência ' . $referenceCode,
                'description' => $description,
                'priority' => $request->priority ?? 'medium',
                'latitude' => $location['coordinates']['latitude'],
                'longitude' => $location['coordinates']['longitude'],
                'address_detail' => $request->address_detail,
                'neighborhood' => $this->locationService->bairroIdentificado($location),
                'neighborhood_id' => $request->neighborhood_id,
                'police_station_id' => $location['police_station_id'],
                'incident_date' => \Carbon\Carbon::now(),
                'reported_by' => $reportedBy,
                'is_anonymous' => $isAnonymous,
                'is_public' => true,
            ]);

            $this->locationService->ensureNoStationForUnresolved($incident, $location);

            return $incident;
        });

        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('incidents', 'public');
                $incident->attachments()->create([
                    'file_path' => $path,
                    'file_type' => 'photo',
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ]);
            }
        }

        NotificationService::notifyNewIncident($incident);

        AuditService::log(
            event: 'incident_created',
            entityType: Incident::class,
            entityId: $incident->id,
            description: "Ocorrência criada: {$incident->reference_code} — {$incident->title}",
            newValues: [
                'reference_code' => $incident->reference_code,
                'title' => $incident->title,
                'category_id' => $incident->category_id,
                'priority' => $incident->priority,
                'status' => $incident->status,
                'is_anonymous' => $incident->is_anonymous,
                'location_status' => $location['status'],
                'bairro' => $this->locationService->bairroIdentificado($location),
                'bairro_declarado' => $request->neighborhood,
                'area_de_atuacao_id' => $location['area_de_atuacao_id'],
                'police_station_id' => $location['police_station_id'],
            ],
        );

        // Antes: redirect()->route('citizen.incidents.show', $incident)->with('success', ...)
        // O React navega para /citizen/incidents/{id}.
        return response()->json([
            'message' => 'Denúncia registada com sucesso. Referência: ' . $incident->reference_code,
            'incident' => $incident,
            'location_status' => $location['status'],
            'bairro' => $this->locationService->bairroIdentificado($location),
        ], 201);
    }

    public function show(Incident $incident): JsonResponse
    {
        if ((int) $incident->reported_by !== (int) auth()->id()) {
            abort(403);
        }
        // attachments: a página mostra os anexos (na view Blade eram carregados sob demanda).
        $incident->load(['category', 'updates.user:id,name', 'attachments']);

        return response()->json([
            'incident' => $incident,
        ]);
    }

    public function myIncidents(): JsonResponse
    {
        $incidents = auth()->user()->reportedIncidents()
            ->with('category')
            ->latest()
            ->paginate(15);

        return response()->json([
            'incidents' => $incidents,
        ]);
    }
}
