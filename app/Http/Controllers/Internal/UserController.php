<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = User::select([
            'id',
            'name',
            'email',
            'status',
            'police_station_id',
            'district_id',
            'agent_number',
            'created_at',
        ])
            ->with(['policeStation:id,name'])
            ->orderBy('id');

        if ($request->filled('role')) {
            $query->role($request->string('role'));
        }

        if ($request->filled('police_station_id')) {
            $query->where('police_station_id', $request->integer('police_station_id'));
        }

        if ($request->filled('district_id')) {
            $query->where('district_id', $request->integer('district_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        $users = $query->paginate($request->integer('per_page', 15));

        $users->getCollection()->transform(fn ($user) => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'status' => $user->status,
            'police_station_id' => $user->police_station_id,
            'police_station_name' => $user->policeStation?->name,
            'district_id' => $user->district_id,
            'agent_number' => $user->agent_number,
            'roles' => $user->getRoleNames()->toArray(),
            'created_at' => $user->created_at,
        ]);

        return response()->json([
            'success' => true,
            'data' => $users,
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $user = User::select([
            'id',
            'name',
            'email',
            'status',
            'phone',
            'police_station_id',
            'district_id',
            'agent_number',
            'address',
            'created_at',
            'updated_at',
        ])
            ->with(['policeStation:id,name'])
            ->find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $user,
        ]);
    }
}
