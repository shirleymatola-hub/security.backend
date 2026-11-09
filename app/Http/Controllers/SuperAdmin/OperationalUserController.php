<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SuperAdmin\OperationalDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OperationalUserController extends Controller
{
    public function __construct(private OperationalDataService $data)
    {
    }

    /** GET /api/super-admin/operational-users?role=&status=&district_id=&police_station_id=&page= */
    public function index(Request $request): JsonResponse
    {
        $users = $this->data
            ->usersQuery($request->only(['role', 'status', 'district_id', 'police_station_id']))
            ->paginate(15);

        $users->getCollection()->transform(fn (User $u) => $this->data->userRow($u));

        return response()->json([
            'users' => $users,
            'roles' => OperationalDataService::ROLES,
            'statuses' => OperationalDataService::USER_STATUSES,
            'districts' => $this->data->districtOptions(),
            'stations' => $this->data->stationOptions(),
        ]);
    }

    /** GET /api/super-admin/operational-users/{id} */
    public function show(int $id): JsonResponse
    {
        $user = User::with(['policeStation:id,name,district_id', 'roles:id,name'])->find($id);

        if (! $user) {
            return response()->json(['message' => 'Utilizador não encontrado.'], 404);
        }

        return response()->json([
            'user' => $this->data->userRow($user) + ['address' => $user->address],
        ]);
    }
}
