<?php

namespace App\Http\Controllers\Citizen;

use App\Http\Controllers\Controller;
use App\Services\ReminderService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        protected ReminderService $reminderService
    ) {}

    public function index()
    {
        $user = auth()->user();

        $myIncidents = $user->reportedIncidents()
            ->with('category')
            ->latest()
            ->limit(10)
            ->get();

        $stats = [
            'total' => $user->reportedIncidents()->count(),
            'pending' => $user->reportedIncidents()->where('status', 'pending')->count(),
            'investigating' => $user->reportedIncidents()->where('status', 'investigating')->count(),
            'resolved' => $user->reportedIncidents()->where('status', 'resolved')->count(),
        ];

        $reminders = $this->reminderService->getReminders($user);

        return response()->json([
            'myIncidents' => $myIncidents,
            'stats' => $stats,
            'reminders' => $reminders['reminders'],
        ]);
    }
}
