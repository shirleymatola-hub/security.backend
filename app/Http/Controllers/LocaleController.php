<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    /**
     * Guarda o idioma preferido do utilizador (pt/en).
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'locale' => 'required|in:pt,en',
        ]);

        $request->user()->update($validated);

        return response()->json(['locale' => $validated['locale']]);
    }
}
