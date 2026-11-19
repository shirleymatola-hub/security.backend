<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\SuperAdmin\SubunidadePoliciaService;
use Illuminate\Http\JsonResponse;

class SubunidadePoliciaController extends Controller
{
    public function __construct(private SubunidadePoliciaService $service)
    {
    }

    public function index(): JsonResponse
    {
        $subunidades = $this->service->all();

        return response()->json([
            'subunidades' => $subunidades,
            'meta' => ['total' => count($subunidades)],
        ]);
    }
}
