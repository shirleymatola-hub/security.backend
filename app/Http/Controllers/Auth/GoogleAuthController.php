<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;

/**
 * Login com Google (apenas cidadãos), sem sessão:
 *
 *  1. O browser abre  GET /api/auth/google/redirect  → Google.
 *  2. O Google volta a GET /api/auth/google/callback.
 *  3. A API redireciona o browser para o frontend:
 *       - sucesso:            FRONTEND_URL/auth/callback#token=...
 *       - erro:               FRONTEND_URL/login?error=...
 *       - email já existente: FRONTEND_URL/auth/google/link?key=...
 *         (o frontend pede a palavra-passe e chama POST /api/auth/google/link)
 */
class GoogleAuthController extends Controller
{
    private const OPERATIONAL_ROLES = [
        'admin',
        'manager',
        'police',
        'post_commander',
        'squad_commander',
        'district_commander',
        'sernic_officer',
    ];

    private const LINK_CACHE_PREFIX = 'google_link:';
    private const LINK_TTL_MINUTES = 15;

    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')
            ->stateless()
            ->scopes(['openid', 'email', 'profile'])
            ->redirect();
    }

    public function callback(): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();
        } catch (\Exception $e) {
            Log::error('[GOOGLE_OAUTH] Socialite::user() failed', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);

            return $this->failed('Autenticação com Google falhou. Tente novamente.');
        }

        $googleId = $googleUser->getId();
        $email = $googleUser->getEmail();

        if (empty($googleId) || empty($email)) {
            return $this->failed('Dados incompletos recebidos do Google.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->failed('Email inválido recebido do Google.');
        }

        try {
            $existingUser = User::where('google_id', $googleId)->first();
            $emailUser = $existingUser ? null : User::where('email', $email)->first();
        } catch (\Exception $e) {
            Log::error('[GOOGLE_OAUTH] user lookup failed', ['message' => $e->getMessage()]);

            return $this->failed('Autenticação com Google falhou. Contacte o administrador.');
        }

        if ($existingUser) {
            return $this->handleExistingGoogleUser($existingUser);
        }

        if ($emailUser) {
            return $this->handleExistingEmailUser($emailUser, $googleId, $googleUser);
        }

        return $this->createNewCitizen($googleUser);
    }

    private function handleExistingGoogleUser(User $user): RedirectResponse
    {
        if ($this->isOperationalUser($user)) {
            AuditService::log(
                event: 'login_failed',
                entityType: User::class,
                entityId: $user->id,
                description: "Tentativa de login Google bloqueada: conta operacional ({$user->email})",
                newValues: ['email' => $user->email, 'reason' => 'operational_account'],
            );

            return $this->failed('Esta conta não pode ser acedida via Google.');
        }

        if ($user->status !== 'active') {
            return $this->failed('Conta inativa. Contacte o administrador.');
        }

        AuditService::log(
            event: 'login_google',
            entityType: User::class,
            entityId: $user->id,
            description: "Login via Google: {$user->email}",
            userId: $user->id,
        );

        return $this->succeeded($user);
    }

    private function handleExistingEmailUser(User $user, string $googleId, SocialiteUser $googleUser): RedirectResponse
    {
        if ($this->isOperationalUser($user)) {
            AuditService::log(
                event: 'login_failed',
                entityType: User::class,
                entityId: $user->id,
                description: "Tentativa de login Google bloqueada: email pertence a conta operacional",
                newValues: ['email' => $user->email, 'reason' => 'operational_email_collision'],
            );

            return $this->failed('Este email está associado a uma conta que não pode ser acedida via Google.');
        }

        if ($user->status !== 'active') {
            return $this->failed('Conta inativa. Contacte o administrador.');
        }

        $key = Str::random(48);
        Cache::put(self::LINK_CACHE_PREFIX . $key, [
            'user_id' => $user->id,
            'google_id' => $googleId,
            'google_name' => $googleUser->getName(),
            'email' => $user->email,
        ], now()->addMinutes(self::LINK_TTL_MINUTES));

        return redirect()->away(frontend_url('/auth/google/link?key=' . urlencode($key)));
    }

    /**
     * Dados para o ecrã "associar conta Google" do frontend.
     */
    public function linkInfo(string $key): JsonResponse
    {
        $pending = Cache::get(self::LINK_CACHE_PREFIX . $key);

        if (!$pending) {
            return response()->json([
                'message' => 'Sessão expirada. Inicie o login com Google novamente.',
            ], 410);
        }

        return response()->json([
            'name' => $pending['google_name'],
            'email' => $pending['email'],
        ]);
    }

    public function processLink(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'key' => 'required|string',
            'password' => 'required',
        ]);

        $cacheKey = self::LINK_CACHE_PREFIX . $validated['key'];
        $pending = Cache::get($cacheKey);

        if (!$pending) {
            throw ValidationException::withMessages([
                'password' => 'Sessão expirada. Inicie o login com Google novamente.',
            ]);
        }

        $user = User::find($pending['user_id']);

        if (!$user) {
            throw ValidationException::withMessages(['password' => 'Conta não encontrada.']);
        }

        if (!Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages(['password' => 'Palavra-passe incorreta.']);
        }

        $user->update([
            'google_id' => $pending['google_id'],
            'auth_type' => 'google',
        ]);

        Cache::forget($cacheKey);

        AuditService::log(
            event: 'account_linked',
            entityType: User::class,
            entityId: $user->id,
            description: "Conta Google associada a: {$user->email}",
            newValues: ['auth_type' => 'google'],
            userId: $user->id,
        );

        AuditService::log(
            event: 'login_google',
            entityType: User::class,
            entityId: $user->id,
            description: "Login via Google (conta associada): {$user->email}",
            userId: $user->id,
        );

        return AuthController::tokenResponse($user);
    }

    private function createNewCitizen(SocialiteUser $googleUser): RedirectResponse
    {
        try {
            $user = User::create([
                'name' => $googleUser->getName() ?? 'Utilizador Google',
                'email' => $googleUser->getEmail(),
                'google_id' => $googleUser->getId(),
                'auth_type' => 'google',
                'password' => Hash::make(Str::random(64)),
                'email_verified_at' => !empty($googleUser->user['email_verified']) ? now() : null,
                'status' => 'active',
            ]);

            $user->assignRole('citizen');
        } catch (\Exception $e) {
            Log::error('[GOOGLE_OAUTH] create citizen failed', [
                'message' => $e->getMessage(),
                'email' => $googleUser->getEmail(),
            ]);

            return $this->failed('Não foi possível criar a conta. Contacte o administrador.');
        }

        AuditService::log(
            event: 'login_google',
            entityType: User::class,
            entityId: $user->id,
            description: "Novo cidadão criado via Google: {$user->email}",
            newValues: [
                'name' => $user->name,
                'email' => $user->email,
                'auth_type' => 'google',
                'email_verified' => !empty($googleUser->user['email_verified']),
            ],
            userId: $user->id,
        );

        AuditService::log(
            event: 'role_assigned',
            entityType: User::class,
            entityId: $user->id,
            description: "Role 'citizen' atribuída a: {$user->email} (via Google)",
            newValues: ['role' => 'citizen', 'via' => 'google'],
            userId: $user->id,
        );

        return $this->succeeded($user);
    }

    private function succeeded(User $user): RedirectResponse
    {
        $token = $user->createToken('smp-spa-google')->plainTextToken;

        // O token vai no fragmento (#) para não ficar em logs de servidores.
        return redirect()->away(frontend_url('/auth/callback#token=' . urlencode($token)));
    }

    private function failed(string $message): RedirectResponse
    {
        return redirect()->away(frontend_url('/login?error=' . urlencode($message)));
    }

    private function isOperationalUser(User $user): bool
    {
        return $user->hasAnyRole(self::OPERATIONAL_ROLES);
    }
}
