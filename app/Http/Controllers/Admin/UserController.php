<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\PoliceStation;
use App\Models\District;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    /**
     * GET /api/admin/users — lista paginada (admin/users/index.blade.php).
     * Cada utilizador inclui "roles" (o perfil mostrado é roles[0].name,
     * equivalente a getRoleNames()->first()).
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::with(['policeStation', 'roles']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('role')) {
            $query->role($request->role);
        }

        $users = $query->orderBy('id', 'asc')->paginate(15);

        return response()->json([
            'users' => $users,
            'roles' => Role::all(),
        ]);
    }

    /**
     * GET /api/admin/users/create — dados do formulário de criação.
     */
    public function create(): JsonResponse
    {
        return response()->json([
            'stations' => PoliceStation::where('is_active', true)->get(),
            'roles' => Role::all(),
            'districts' => District::all(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'phone' => 'nullable|string|max:20',
            'password' => 'required|string|min:8|confirmed',
            'police_station_id' => 'nullable|exists:police_stations,id',
            'role' => 'required|exists:roles,name',
            'agent_number' => 'nullable|required_if:role,police|string|max:50',
            'district_id' => 'nullable|exists:districts,id',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => Hash::make($validated['password']),
            'police_station_id' => $validated['police_station_id'] ?? null,
            'agent_number' => $validated['role'] === 'police' ? ($validated['agent_number'] ?? null) : null,
            'district_id' => $validated['district_id'] ?? null,
        ]);

        $user->assignRole($validated['role']);

        AuditService::log(
            event: 'role_assigned',
            entityType: User::class,
            entityId: $user->id,
            description: "Role '{$validated['role']}' atribuída a: {$user->email}",
            newValues: ['role' => $validated['role']],
        );

        return response()->json([
            'message' => 'Utilizador criado com sucesso.',
            'user' => $user->load(['policeStation', 'roles']),
        ], 201);
    }

    /**
     * GET /api/admin/users/{user}/edit — utilizador (com roles) e dados do formulário.
     */
    public function edit(User $user): JsonResponse
    {
        return response()->json([
            'user' => $user->load('roles'),
            'stations' => PoliceStation::where('is_active', true)->get(),
            'roles' => Role::all(),
            'districts' => District::all(),
        ]);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => "required|email|unique:users,email,{$user->id}",
            'phone' => 'nullable|string|max:20',
            'password' => 'nullable|string|min:8|confirmed',
            'police_station_id' => 'nullable|exists:police_stations,id',
            'status' => 'required|in:active,inactive,suspended',
            'role' => 'required|exists:roles,name',
            'district_id' => 'nullable|exists:districts,id',
            // Correção: o formulário original mostrava o campo mas o valor era ignorado.
            'agent_number' => 'nullable|string|max:50',
        ]);

        $data = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'police_station_id' => $validated['police_station_id'] ?? null,
            'district_id' => $validated['district_id'] ?? null,
            'status' => $validated['status'],
        ];

        if ($validated['role'] === 'police') {
            $data['agent_number'] = $validated['agent_number'] ?? null;
        }

        if (!empty($validated['password'])) {
            $data['password'] = Hash::make($validated['password']);
        }

        $user->update($data);
        $user->syncRoles([$validated['role']]);

        AuditService::log(
            event: 'role_removed',
            entityType: User::class,
            entityId: $user->id,
            description: "Roles sincronizadas para: {$user->email}",
            newValues: ['roles' => [$validated['role']]],
        );

        return response()->json([
            'message' => 'Utilizador atualizado com sucesso.',
            'user' => $user->fresh()->load(['policeStation', 'roles']),
        ]);
    }

    public function destroy(User $user): JsonResponse
    {
        if (auth()->id() === $user->id) {
            return response()->json(['message' => 'Não pode eliminar a sua própria conta.'], 422);
        }

        $user->delete();

        return response()->json(['message' => 'Utilizador eliminado com sucesso.']);
    }
}
