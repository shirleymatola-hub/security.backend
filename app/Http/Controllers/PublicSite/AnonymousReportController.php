<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\Incident;
use App\Models\Neighborhood;
use App\Services\AuditService;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Denúncia anónima (antigas closures GET/POST /denuncia-anonima do routes/web.php).
 */
class AnonymousReportController extends Controller
{
    /**
     * Bairros (com distrito) para o formulário da denúncia anónima.
     */
    public function neighborhoods(): JsonResponse
    {
        $neighborhoods = Neighborhood::with('district')->get()
            // A geometria PostGIS/limites não são usados pelo formulário (só id e nome).
            ->makeHidden(['geometry', 'boundary']);

        return response()->json([
            'neighborhoods' => $neighborhoods,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'description' => 'required_without:audio_file|string|nullable',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'address_detail' => 'nullable|string|max:255',
            'neighborhood' => 'nullable|string|max:255',
            'neighborhood_id' => 'nullable|exists:neighborhoods,id',
            'attachments' => 'nullable|array|max:5',
            'attachments.*' => 'file|mimes:jpg,jpeg,png,webp|max:5120',
            'audio_file' => 'nullable|file|mimes:webm,ogg,wav|max:10240',
        ]);

        $description = $validated['description'] ?? null;
        if ($request->hasFile('audio_file')) {
            $audioPath = $request->file('audio_file')->store('incidents/anonymous/audio', 'public');
            $description = $description ? $description . ' [Audio: ' . $audioPath . ']' : '[Audio: ' . $audioPath . ']';
        }

        $referenceCode = 'ANON-' . strtoupper(uniqid());

        $incident = Incident::create([
            'reference_code' => $referenceCode,
            'title' => 'Denúncia Anónima ' . $referenceCode,
            'description' => $description,
            'priority' => 'medium',
            'status' => 'pending',
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'address_detail' => $validated['address_detail'] ?? null,
            'neighborhood' => $validated['neighborhood'] ?? null,
            'neighborhood_id' => $validated['neighborhood_id'] ?? null,
            'incident_date' => Carbon::now(),
            'is_anonymous' => true,
            'is_public' => true,
        ]);

        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('incidents/anonymous', 'public');
                $incident->attachments()->create([
                    'file_path' => $path,
                    'file_type' => 'photo',
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ]);
            }
        }

        $incident->refresh();

        AuditService::log(
            event: 'incident_created',
            entityType: Incident::class,
            entityId: $incident->id,
            description: "Denúncia anónima criada: {$incident->title} ({$incident->reference_code})",
            newValues: [
                'reference_code' => $incident->reference_code,
                'category_id' => $incident->category_id,
                'priority' => $incident->priority,
                'is_anonymous' => true,
            ],
        );

        NotificationService::notifyNewIncident($incident);

        return response()->json([
            'message' => 'Denúncia anónima registada com sucesso. Obrigado pela sua colaboração.',
            'reference_code' => $incident->reference_code,
        ], 201);
    }
}
