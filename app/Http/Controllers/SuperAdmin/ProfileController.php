<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    /** PUT /api/super-admin/profile */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user('super_admin');

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('super_admin_users', 'email')->ignore($user->id)],
        ]);

        $user->update($validated);

        return response()->json([
            'message' => 'Perfil actualizado com sucesso.',
            'user' => $user->toApiArray(),
        ]);
    }

    /** PUT /api/super-admin/profile/password */
    public function updatePassword(Request $request): JsonResponse
    {
        $user = $request->user('super_admin');

        $request->validate([
            'current_password' => 'required',
            'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()],
        ]);

        if (! Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['A password actual está incorreta.'],
            ]);
        }

        $user->update(['password' => $request->password]);

        // Mantém só a sessão atual; as restantes deixam de ser válidas.
        $current = $user->currentAccessToken();
        $user->tokens()->when($current?->id, fn ($q, $id) => $q->where('id', '!=', $id))->delete();

        return response()->json(['message' => 'Password actualizada com sucesso.']);
    }
}
