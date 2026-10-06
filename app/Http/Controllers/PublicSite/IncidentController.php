<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Incident;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Ocorrências públicas (antiga closure GET /ocorrencias do routes/web.php).
 */
class IncidentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Incident::where('is_public', true)->with('category');

        if ($request->query('category_id')) {
            $query->where('category_id', $request->query('category_id'));
        }
        if ($request->query('date_from')) {
            $query->whereDate('created_at', '>=', $request->query('date_from'));
        }
        if ($request->query('date_to')) {
            $query->whereDate('created_at', '<=', $request->query('date_to'));
        }

        $incidents = $query->latest()->paginate(15)->withQueryString();
        $categories = Category::where('is_active', true)->get();

        return response()->json([
            'incidents' => $incidents,
            'categories' => $categories,
        ]);
    }
}
