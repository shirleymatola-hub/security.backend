<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Perfil do utilizador autenticado — comum a todos os perfis
 * (substitui os ProfileController de cada módulo, que eram idênticos).
 * O cidadão pode também guardar morada e coordenadas.
 */
class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'user' => new UserResource($request->user()->load('policeStation')),
        ]);
    }

    /**
     * Aceita multipart/form-data (foto). O frontend envia POST com _method=PUT.
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $rules = [
            'name' => 'required|string|max:255',
            'email' => "required|email|unique:users,email,{$user->id}",
            'phone' => 'nullable|string|max:20',
            'locale' => 'nullable|in:pt,en',
            'profile_photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'current_password' => 'nullable|required_with:new_password|string',
            'new_password' => 'nullable|string|min:8|confirmed',
        ];

        if ($user->hasRole('citizen')) {
            $rules += [
                'address' => 'nullable|string|max:255',
                'latitude' => 'nullable|numeric|between:-90,90',
                'longitude' => 'nullable|numeric|between:-180,180',
            ];
        }

        $validated = $request->validate($rules);

        if (!empty($validated['current_password']) && !empty($validated['new_password'])) {
            if (!Hash::check($validated['current_password'], $user->password)) {
                throw ValidationException::withMessages([
                    'current_password' => 'A palavra-passe atual está incorreta.',
                ]);
            }
        }

        $data = collect($validated)->except(['profile_photo', 'current_password', 'new_password'])->filter()->toArray();

        if ($request->hasFile('profile_photo')) {
            if ($user->profile_photo && Storage::disk('public')->exists($user->profile_photo)) {
                Storage::disk('public')->delete($user->profile_photo);
            }
            $data['profile_photo'] = $request->file('profile_photo')->store('profile-photos', 'public');
        }

        if (!empty($validated['new_password'])) {
            $data['password'] = Hash::make($validated['new_password']);
        }

        $user->update($data);

        return response()->json([
            'message' => 'Perfil atualizado com sucesso.',
            'user' => new UserResource($user->fresh()->load('policeStation')),
        ]);
    }
}
