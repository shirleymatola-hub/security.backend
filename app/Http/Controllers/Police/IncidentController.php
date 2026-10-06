<?php

namespace App\Http\Controllers\Police;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Incident;
use App\Models\User;
use App\Http\Requests\StoreIncidentUpdateRequest;
use App\Services\NotificationService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IncidentController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();

        if (!$user) {
            abort(403, 'Não autenticado');
        }

        $stationId = auth()->user()->police_station_id;

        $query = Incident::query()
            ->where('police_station_id', $stationId)
            ->with(['category', 'reporter', 'assignee']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->date_from);
        }

        if ($request->filled('time_from')) {
            $query->whereRaw("EXTRACT(HOUR FROM created_at) >= ?", [substr($request->time_from, 0, 2)]);
            $query->whereRaw("EXTRACT(MINUTE FROM created_at) >= ?", [substr($request->time_from, 3, 2)]);
        }

        $incidents = $query->latest()->paginate(15);
        $categories = Category::where('is_active', true)->orderBy('name')->get();

        return response()->json([
            'incidents' => $incidents,
            'categories' => $categories,
        ]);
    }

    public function show(Incident $incident): JsonResponse
    {
        $this->authorize('view', $incident);

        // 'assignee' era carregado de forma lazy pela view ("Atribuído A")
        $incident->load(['category', 'reporter', 'assignee', 'updates.user', 'attachments', 'policeStation']);

        // Antes calculado na view: agentes do posto para o formulário "Atribuir Agente"
        $officers = User::role('police')
            ->where('police_station_id', auth()->user()->police_station_id)
            ->get();

        return response()->json([
            'incident' => $incident,
            'officers' => $officers,
        ]);
    }

    public function update(StoreIncidentUpdateRequest $request, Incident $incident): JsonResponse
    {
        $this->authorize('update', $incident);

        $incident->updates()->create([
            'user_id' => auth()->id(),
            'status' => $request->status,
            'observation' => $request->observation,
        ]);

        $incident->update([
            'status' => $request->status,
        ]);

        if ($request->status === 'resolved') {
            $incident->update(['resolved_at' => now()]);
            NotificationService::notifyResolved($incident, $request->observation);
        } else {
            NotificationService::notifyStatusChanged($incident, $request->observation);
        }

        return response()->json([
            'message' => 'Estado da ocorrência atualizado com sucesso.',
            'incident' => $incident->fresh(),
        ]);
    }

    public function assign(Request $request, Incident $incident): JsonResponse
    {
        $this->authorize('assign', $incident);

        $validated = $request->validate([
            'assigned_to' => 'required|exists:users,id',
        ]);

        $incident->update($validated);

        NotificationService::notifyAssignedOfficer($incident, $validated['assigned_to']);

        $officer = User::find($validated['assigned_to']);
        if ($officer && $officer->hasRole('sernic_officer')) {
            NotificationService::notifyForwardedToSernic($incident, 'Atribuído ao SERNIC para investigação criminal.');
        }

        return response()->json([
            'message' => 'Ocorrência atribuída com sucesso.',
            'incident' => $incident->fresh(),
        ]);
    }
}
