<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\SuperAdminAuditLog;
use App\Models\SuperAdminUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    private const SECURITY_EVENTS = [
        'login_failed',
        'account_locked',
        'account_unlocked',
        'two_factor_enabled',
        'two_factor_disabled',
        'recovery_code_used',
        'password_changed',
        'password_reset_requested',
        'password_reset_completed',
    ];

    private const ADMIN_EVENTS = [
        'invitation_created',
        'invitation_cancelled',
        'profile_updated',
        'recovery_codes_regenerated',
    ];

    /** GET /api/super-admin/audit?search=&user_id=&event=&date_from=&date_to=&ip=&page= */
    public function index(Request $request): JsonResponse
    {
        $query = SuperAdminAuditLog::with('superAdminUser:id,name,email');

        if ($request->filled('user_id')) {
            $query->where('super_admin_user_id', $request->integer('user_id'));
        }

        if ($request->filled('event')) {
            $query->where('event', $request->string('event'));
        }

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->get('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->get('date_to').' 23:59:59');
        }

        if ($request->filled('ip')) {
            $query->where('ip_address', 'ilike', '%'.addcslashes($request->get('ip'), '\\%_').'%');
        }

        if ($request->filled('search')) {
            $like = '%'.addcslashes($request->get('search'), '\\%_').'%';
            $query->where(fn ($q) => $q->where('event', 'ilike', $like)
                ->orWhere('description', 'ilike', $like)
                ->orWhere('entity_type', 'ilike', $like)
                ->orWhere('ip_address', 'ilike', $like));
        }

        $today = now()->toDateString();

        return response()->json([
            'logs' => $query->orderByDesc('created_at')->orderByDesc('id')->paginate(20),
            'users' => SuperAdminUser::select('id', 'name', 'email')->orderBy('name')->get(),
            'events' => SuperAdminAuditLog::select('event')->distinct()->orderBy('event')->pluck('event'),
            'stats' => [
                'total' => SuperAdminAuditLog::count(),
                'today' => SuperAdminAuditLog::whereDate('created_at', $today)->count(),
                'logins' => SuperAdminAuditLog::whereIn('event', ['login_successful', 'login_failed'])->count(),
                'security' => SuperAdminAuditLog::whereIn('event', self::SECURITY_EVENTS)->count(),
                'admin_actions' => SuperAdminAuditLog::whereIn('event', self::ADMIN_EVENTS)->count(),
            ],
        ]);
    }

    /** GET /api/super-admin/audit/{id} */
    public function show(int $id): JsonResponse
    {
        $log = SuperAdminAuditLog::with('superAdminUser:id,name,email')->find($id);

        if (! $log) {
            return response()->json(['message' => 'Evento não encontrado.'], 404);
        }

        return response()->json(['log' => $log]);
    }
}
