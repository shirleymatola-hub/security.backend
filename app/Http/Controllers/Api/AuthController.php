<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Autenticação da SPA por token (Laravel Sanctum).
 * O frontend guarda o token e envia-o em "Authorization: Bearer <token>".
 */
class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if ($user && $user->isLocked()) {
            AuditService::log(
                event: 'login_failed',
                entityType: User::class,
                entityId: $user->id,
                description: "Tentativa de login com conta bloqueada: {$user->email}",
                newValues: ['email' => $user->email, 'reason' => 'account_locked'],
            );

            throw ValidationException::withMessages([
                'email' => 'A sua conta está temporariamente bloqueada. Tente novamente mais tarde.',
            ]);
        }

        if ($user && Hash::check($credentials['password'], $user->password)) {
            $user->recordLogin($request->ip());

            AuditService::log(
                event: 'login_successful',
                entityType: User::class,
                entityId: $user->id,
                description: "Login bem-sucedido: {$user->email}",
                userId: $user->id,
            );

            return $this->tokenResponse($user);
        }

        if ($user) {
            $user->recordFailedAttempt();

            if ($user->isLocked()) {
                AuditService::log(
                    event: 'account_locked',
                    entityType: User::class,
                    entityId: $user->id,
                    description: "Conta bloqueada por múltiplas tentativas falhadas: {$user->email}",
                );
            }
        }

        $email = $credentials['email'] ?? 'unknown';
        AuditService::log(
            event: 'login_failed',
            entityType: User::class,
            entityId: null,
            description: "Tentativa de login falhada para: {$email}",
            newValues: ['email' => $email],
        );

        throw ValidationException::withMessages(['email' => 'Credenciais inválidas.']);
    }

    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|string|max:20',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => Hash::make($validated['password']),
            'status' => 'active',
        ]);

        $user->assignRole('citizen');

        AuditService::log(
            event: 'role_assigned',
            entityType: User::class,
            entityId: $user->id,
            description: "Role 'citizen' atribuída a: {$user->email}",
            newValues: ['role' => 'citizen'],
            userId: $user->id,
        );

        return $this->tokenResponse($user, 201);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => new UserResource($request->user()->load('policeStation')),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        AuditService::log(
            event: 'logout',
            entityType: User::class,
            entityId: $user->id,
            description: "Logout: {$user->email}",
        );

        $token = $user->currentAccessToken();
        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return response()->json(['message' => 'Sessão terminada.']);
    }

    /**
     * Passo 1 da recuperação: gera um código de 6 dígitos.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $validated = $request->validate(['email' => 'required|email']);

        $response = [
            'message' => 'Se o email existir no sistema, receberá um código de recuperação.',
        ];

        $user = User::where('email', $validated['email'])->first();

        if ($user) {
            AuditService::log(
                event: 'password_reset_requested',
                entityType: User::class,
                entityId: $user->id,
                description: "Password reset solicitada por: {$user->email}",
                newValues: ['email' => $user->email],
            );

            $code = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);

            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $user->email],
                ['token' => Hash::make(Str::random(64)), 'code' => $code, 'created_at' => now()]
            );

            // Em desenvolvimento o código é mostrado no ecrã (não há envio de email).
            if (app()->environment('local', 'development')) {
                $response['dev_code'] = $code;
            }
        }

        return response()->json($response);
    }

    /**
     * Passo 2: confirma o código e devolve um token de redefinição.
     */
    public function confirmCode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'code' => 'required|string|size:6',
        ]);

        $resetRecord = DB::table('password_reset_tokens')
            ->where('email', $validated['email'])
            ->where('code', $validated['code'])
            ->first();

        if (!$resetRecord) {
            throw ValidationException::withMessages(['code' => 'Código inválido ou email incorreto.']);
        }

        if (now()->diffInMinutes($resetRecord->created_at, true) > 60) {
            DB::table('password_reset_tokens')->where('email', $validated['email'])->delete();
            throw ValidationException::withMessages(['code' => 'Código expirado. Solicite um novo.']);
        }

        $token = Str::random(64);
        DB::table('password_reset_tokens')
            ->where('email', $validated['email'])
            ->update(['token' => Hash::make($token)]);

        return response()->json([
            'token' => $token,
            'email' => $validated['email'],
        ]);
    }

    /**
     * Passo 3: define a nova palavra-passe.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $resetRecord = DB::table('password_reset_tokens')
            ->where('email', $validated['email'])
            ->first();

        if (!$resetRecord || !Hash::check($validated['token'], $resetRecord->token)) {
            throw ValidationException::withMessages(['email' => 'Token inválido ou expirado.']);
        }

        if (now()->diffInMinutes($resetRecord->created_at, true) > 60) {
            DB::table('password_reset_tokens')->where('email', $validated['email'])->delete();
            throw ValidationException::withMessages(['email' => 'Token expirado. Solicite um novo.']);
        }

        $user = User::where('email', $validated['email'])->first();
        if (!$user) {
            throw ValidationException::withMessages(['email' => 'Utilizador não encontrado.']);
        }

        $user->update(['password' => Hash::make($validated['password'])]);
        DB::table('password_reset_tokens')->where('email', $validated['email'])->delete();

        AuditService::log(
            event: 'password_reset_completed',
            entityType: User::class,
            entityId: $user->id,
            description: "Password redefinida via token para: {$user->email}",
            userId: $user->id,
        );

        return response()->json([
            'message' => 'Palavra-passe redefinida com sucesso. Pode iniciar sessão.',
        ]);
    }

    /**
     * Cria um token Sanctum e devolve-o com os dados do utilizador.
     */
    public static function tokenResponse(User $user, int $status = 200): JsonResponse
    {
        $token = $user->createToken('smp-spa')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => new UserResource($user->load('policeStation')),
        ], $status);
    }
}
