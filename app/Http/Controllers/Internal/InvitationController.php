<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\InternalUserInvitation;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class InvitationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = InternalUserInvitation::query()
            ->with(['policeStation:id,name', 'district:id,name']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('email')) {
            $query->where('email', 'like', "%{$request->email}%");
        }

        $invitations = $query->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $invitations,
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $invitation = InternalUserInvitation::with(['policeStation:id,name', 'district:id,name'])
            ->find($id);

        if (!$invitation) {
            return response()->json([
                'success' => false,
                'message' => 'Convite não encontrado.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $invitation,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'role' => 'required|string|in:police,post_commander,squad_commander,district_commander,manager,sernic_officer',
            'police_station_id' => 'nullable|exists:police_stations,id',
            'district_id' => 'nullable|exists:districts,id',
        ]);

        $existingUser = User::where('email', $validated['email'])->first();
        if ($existingUser) {
            return response()->json([
                'success' => false,
                'message' => 'Já existe um utilizador com este email no sistema.',
            ], 422);
        }

        $pendingInvitation = InternalUserInvitation::where('email', $validated['email'])
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->first();

        if ($pendingInvitation) {
            return response()->json([
                'success' => false,
                'message' => 'Já existe um convite pendente para este email.',
                'data' => [
                    'invitation_id' => $pendingInvitation->id,
                    'expires_at' => $pendingInvitation->expires_at->toIso8601String(),
                ],
            ], 422);
        }

        $token = InternalUserInvitation::generateToken();
        $expiryHours = (int) env('INVITATION_EXPIRES_HOURS', 72);

        $invitation = InternalUserInvitation::createFromPlainText($token, [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'police_station_id' => $validated['police_station_id'] ?? null,
            'district_id' => $validated['district_id'] ?? null,
            'invited_by' => $request->attributes->get('internal_api_token')->token_name ?? 'super-admin',
            'expires_at' => now()->addHours($expiryHours),
            'status' => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Convite criado com sucesso.',
            'data' => [
                'id' => $invitation->id,
                'token' => $token,
                'email' => $invitation->email,
                'name' => $invitation->name,
                'role' => $invitation->role,
                'expires_at' => $invitation->expires_at->toIso8601String(),
                'status' => $invitation->status,
            ],
        ], 201);
    }

    public function cancel(int $id): JsonResponse
    {
        $invitation = InternalUserInvitation::find($id);

        if (!$invitation) {
            return response()->json([
                'success' => false,
                'message' => 'Convite não encontrado.',
            ], 404);
        }

        if (!$invitation->isPending()) {
            return response()->json([
                'success' => false,
                'message' => 'Apenas convites pendentes podem ser cancelados.',
            ], 422);
        }

        $invitation->update(['status' => 'cancelled']);

        return response()->json([
            'success' => true,
            'message' => 'Convite cancelado com sucesso.',
        ]);
    }

    public function acceptByToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $tokenHash = hash('sha256', $validated['token']);

        $invitation = InternalUserInvitation::where('token_hash', $tokenHash)->first();

        if (!$invitation) {
            return response()->json([
                'success' => false,
                'message' => 'Convite inválido.',
            ], 404);
        }

        if (!$invitation->canBeAccepted()) {
            $message = match ($invitation->status) {
                'accepted' => 'Este convite já foi utilizado.',
                'cancelled' => 'Este convite foi cancelado.',
                default => 'Este convite expirou.',
            };

            return response()->json([
                'success' => false,
                'message' => $message,
            ], 422);
        }

        $existingUser = User::where('email', $invitation->email)->first();
        if ($existingUser) {
            return response()->json([
                'success' => false,
                'message' => 'Já existe uma conta com este email.',
            ], 422);
        }

        $user = User::create([
            'name' => $invitation->name,
            'email' => $invitation->email,
            'password' => Hash::make($validated['password']),
            'police_station_id' => $invitation->police_station_id,
            'district_id' => $invitation->district_id,
            'status' => 'active',
        ]);

        $user->assignRole($invitation->role);

        AuditService::log(
            event: 'role_assigned',
            entityType: User::class,
            entityId: $user->id,
            description: "Role '{$invitation->role}' atribuída a: {$user->email} (via convite)",
            newValues: ['role' => $invitation->role, 'via' => 'invitation'],
        );

        $invitation->update([
            'status' => 'accepted',
            'accepted_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Conta criada com sucesso. Pode iniciar sessão.',
        ]);
    }

    public function showByToken(string $token): JsonResponse
    {
        $tokenHash = hash('sha256', $token);

        $invitation = InternalUserInvitation::where('token_hash', $tokenHash)
            ->select(['id', 'email', 'name', 'role', 'status', 'expires_at'])
            ->first();

        if (!$invitation) {
            return response()->json([
                'success' => false,
                'message' => 'Convite inválido.',
            ], 404);
        }

        if (!$invitation->canBeAccepted()) {
            $message = match ($invitation->status) {
                'accepted' => 'Este convite já foi utilizado.',
                'cancelled' => 'Este convite foi cancelado.',
                default => 'Este convite expirou.',
            };

            return response()->json([
                'success' => false,
                'message' => $message,
                'data' => [
                    'status' => $invitation->status,
                ],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'email' => $invitation->email,
                'name' => $invitation->name,
                'role' => $invitation->role,
                'expires_at' => $invitation->expires_at->toIso8601String(),
            ],
        ]);
    }
}
