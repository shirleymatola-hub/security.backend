<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\PoliceStation;
use App\Models\Category;
use App\Services\IncidentService;
use App\Services\ReminderService;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __construct(
        protected IncidentService $incidentService,
        protected ReminderService $reminderService
    ) {}

    /**
     * GET /api/admin/dashboard — dados de admin/dashboard.blade.php.
     * O mapa (ocorrências, postos, cobertura, bairros) é carregado pelo
     * frontend a partir de /api/map/... tal como na view original.
     */
    public function index(): JsonResponse
    {
        $stats = $this->incidentService->getStatistics();
        $reminders = $this->reminderService->getReminders(auth()->user());

        return response()->json([
            'stats' => $stats,
            'totalUsers' => User::count(),
            'totalStations' => PoliceStation::count(),
            'totalCategories' => Category::count(),
            'reminders' => $reminders['reminders'],
        ]);
    }
}
