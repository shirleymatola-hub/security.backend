<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Tema, idioma e menu fixo são preferências guardadas no browser (frontend).
 * A API só fornece a informação da secção "Sobre".
 */
class SettingsController extends Controller
{
    /** GET /api/super-admin/settings */
    public function index(): JsonResponse
    {
        return response()->json([
            'about' => [
                'framework' => 'Laravel '.app()->version(),
                'environment' => ucfirst((string) config('app.env', 'production')),
                'year' => (int) now()->format('Y'),
            ],
        ]);
    }
}
