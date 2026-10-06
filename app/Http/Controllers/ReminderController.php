<?php

namespace App\Http\Controllers;

use App\Services\ReminderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReminderController extends Controller
{
    public function __construct(
        protected ReminderService $reminderService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $this->reminderService->getReminders($user);

        return response()->json($data);
    }
}
