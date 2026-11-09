<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\InternalUserInvitation;
use App\Models\User;
use App\Services\SuperAdmin\OperationalDataService;
use App\Services\SuperAdmin\SuperAdminAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Convites para utilizadores operacionais. O convidado aceita na página
 * pública /convite/{token} do frontend (POST /api/internal/invite/accept).
 */
class InvitationController extends Controller
{
    public function __construct(private OperationalDataService $data)
    {
    }

    /** GET /api/super-admin/invitations?status=&page= */
    public function index(Request $request): JsonResponse
    {
        $query = InternalUserInvitation::query()
            ->with(['policeStation:id,name', 'district:id,name'])
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        $invitations = $query->paginate(15);
        $invitations->getCollection()->transform(fn ($i) => $this->present($i));

        return response()->json($invitations);
    }

    /** GET /api/super-admin/invitations/options — dados do formulário de novo convite. */
    public function options(): JsonResponse
    {
        return response()->json([
            'roles' => OperationalDataService::INVITABLE_ROLES,
            'stations' => $this->data->stationOptions(),
            'districts' => $this->data->districtOptions(),
        ]);
    }

    /** POST /api/super-admin/invitations */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'role' => ['required', Rule::in(array_keys(OperationalDataService::INVITABLE_ROLES))],
            'police_station_id' => 'nullable|integer|exists:police_stations,id',
            'district_id' => 'nullable|integer|exists:districts,id',
        ]);

        if (User::where('email', $validated['email'])->exists()) {
            return response()->json([
                'message' => 'Já existe um utilizador com este email no sistema.',
                'errors' => ['email' => ['Já existe um utilizador com este email no sistema.']],
            ], 422);
        }

        $pending = InternalUserInvitation::where('email', $validated['email'])
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->first();

        if ($pending) {
            return response()->json([
                'message' => 'Já existe um convite pendente para este email.',
                'errors' => ['email' => ['Já existe um convite pendente para este email.']],
                'invitation_id' => $pending->id,
            ], 422);
        }

        $admin = $request->user('super_admin');
        $token = InternalUserInvitation::generateToken();

        $invitation = InternalUserInvitation::createFromPlainText($token, [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'police_station_id' => $validated['police_station_id'] ?? null,
            'district_id' => $validated['district_id'] ?? null,
            'invited_by' => 'super-admin:'.$admin->email,
            'expires_at' => now()->addHours(config('super_admin.invitation_expires_hours', 72)),
            'status' => 'pending',
        ]);

        SuperAdminAuditService::log(
            event: 'invitation_created',
            entityType: InternalUserInvitation::class,
            entityId: $invitation->id,
            description: "Convite criado: {$validated['name']} ({$validated['email']}) — role: {$validated['role']}",
            newValues: [
                'name' => $validated['name'],
                'email' => $validated['email'],
                'role' => $validated['role'],
                'police_station_id' => $validated['police_station_id'] ?? null,
                'district_id' => $validated['district_id'] ?? null,
            ],
        );

        // O token só é mostrado agora: na base de dados fica apenas o hash.
        return response()->json([
            'message' => 'Convite criado com sucesso.',
            'invitation' => $this->present($invitation->load(['policeStation:id,name', 'district:id,name'])),
            'invite_url' => frontend_url('/convite/'.$token),
        ], 201);
    }

    /** GET /api/super-admin/invitations/{id} */
    public function show(int $id): JsonResponse
    {
        $invitation = InternalUserInvitation::with(['policeStation:id,name', 'district:id,name'])->find($id);

        if (! $invitation) {
            return response()->json(['message' => 'Convite não encontrado.'], 404);
        }

        return response()->json(['invitation' => $this->present($invitation)]);
    }

    /** POST /api/super-admin/invitations/{id}/cancel */
    public function cancel(int $id): JsonResponse
    {
        $invitation = InternalUserInvitation::find($id);

        if (! $invitation) {
            return response()->json(['message' => 'Convite não encontrado.'], 404);
        }

        if (! $invitation->isPending()) {
            return response()->json(['message' => 'Apenas convites pendentes podem ser cancelados.'], 422);
        }

        $invitation->update(['status' => 'cancelled']);

        SuperAdminAuditService::log(
            event: 'invitation_cancelled',
            entityType: InternalUserInvitation::class,
            entityId: $invitation->id,
            description: "Convite #{$invitation->id} cancelado ({$invitation->email})",
        );

        return response()->json(['message' => 'Convite cancelado com sucesso.']);
    }

    private function present(InternalUserInvitation $i): array
    {
        return [
            'id' => $i->id,
            'name' => $i->name,
            'email' => $i->email,
            'role' => $i->role,
            'role_label' => OperationalDataService::ROLES[$i->role] ?? $i->role,
            'status' => $i->status,
            'is_expired' => $i->isPending() && $i->isExpired(),
            'invited_by' => $i->invited_by,
            'expires_at' => $i->expires_at,
            'accepted_at' => $i->accepted_at,
            'created_at' => $i->created_at,
            'police_station' => $i->policeStation ? ['id' => $i->policeStation->id, 'name' => $i->policeStation->name] : null,
            'district' => $i->district ? ['id' => $i->district->id, 'name' => $i->district->name] : null,
        ];
    }
}
