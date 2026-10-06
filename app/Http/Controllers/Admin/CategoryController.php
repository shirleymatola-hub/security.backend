<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $categories = Category::withCount('incidents')
            ->latest()
            ->paginate(15);

        return response()->json([
            'categories' => $categories,
        ]);
    }

    // create(): removido — o formulário de criação não precisa de dados do servidor.

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|unique:categories',
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:50',
            'color' => 'required|string|max:7',
        ]);

        $category = Category::create($validated);

        return response()->json([
            'message' => 'Categoria criada com sucesso.',
            'category' => $category,
        ], 201);
    }

    /**
     * GET /api/admin/categories/{category}/edit
     */
    public function edit(Category $category): JsonResponse
    {
        return response()->json([
            'category' => $category,
        ]);
    }

    public function update(Request $request, Category $category): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => "required|string|unique:categories,slug,{$category->id}",
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:50',
            'color' => 'required|string|max:7',
            'is_active' => 'boolean',
        ]);

        $category->update($validated);

        return response()->json([
            'message' => 'Categoria atualizada com sucesso.',
            'category' => $category->fresh(),
        ]);
    }

    public function show(Category $category): JsonResponse
    {
        $category->load(['incidents' => function ($q) {
            $q->with('policeStation')->latest('incident_date')->limit(10);
        }]);

        $category->loadCount('incidents');

        return response()->json([
            'category' => $category,
        ]);
    }

    public function destroy(Category $category): JsonResponse
    {
        $category->delete();

        return response()->json(['message' => 'Categoria eliminada com sucesso.']);
    }
}
