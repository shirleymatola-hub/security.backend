<?php

namespace App\Http\Controllers\Sernic;

use App\Http\Controllers\Controller;
use App\Models\Incident;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IncidentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();

        // policeStation: a lista mostra o "Posto de Origem" (na view Blade era carregado sob demanda).
        $query = Incident::where('assigned_to', $user->id)
            ->with(['category', 'reporter:id,name', 'policeStation:id,name']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('reference_code', 'like', "%{$search}%")
                  ->orWhere('title', 'like', "%{$search}%");
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

        $incidents = $query->latest()->paginate(15);

        return response()->json([
            'incidents' => $incidents,
        ]);
    }

    public function show(Incident $incident): JsonResponse
    {
        // Apenas os campos dos utilizadores que a página mostra.
        $incident->load([
            'category',
            'reporter:id,name,email,phone',
            'assignee:id,name',
            'updates.user:id,name',
            'attachments',
            'policeStation:id,name',
        ]);

        return response()->json([
            'incident' => $incident,
        ]);
    }
}
