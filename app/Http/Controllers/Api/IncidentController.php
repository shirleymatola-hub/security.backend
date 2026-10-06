<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Incident;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IncidentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Incident::where('is_public', true)
            ->with('category:id,name,slug,color,icon');

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->filled('neighborhood')) {
            $query->where('neighborhood', $request->neighborhood);
        }
        $incidents = $query->latest('incident_date')->get();

        return response()->json($incidents);
    }

    public function show(Request $request, Incident $incident): JsonResponse
    {
        if (!$incident->is_public && !$request->user()->can('view', $incident)) {
            return response()->json(['error' => 'Não autorizado.'], 403);
        }

        $incident->load(['category', 'updates.user:id,name', 'attachments']);

        return response()->json($incident);
    }

    public function categories(): JsonResponse
    {
        $categories = Category::where('is_active', true)
            ->withCount('incidents')
            ->get();

        return response()->json($categories);
    }
}
