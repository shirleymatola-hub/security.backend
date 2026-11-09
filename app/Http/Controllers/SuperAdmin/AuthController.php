<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\SuperAdminUser;
use App\Notifications\SuperAdminResetPassword;
use App\Services\SuperAdmin\SuperAdminAuditService;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use PragmaRX\Google2FA\Google2FA;

/**
 * Autenticação do painel Super Admin por token Sanctum (guard "super_admin").
 * Substitui LoginController, TwoFactorController::verify e ForgotPasswordController
 * do antigo projeto super-admin (que usavam sessões).
 */
class AuthController extends Controller
{
    /** POST /api/super-admin/auth/login */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'remember' => 'sometimes|boolean',
        ]);

        $user = SuperAdminUser::where('email', $request->email)->first();

        if (! $user) {
            $this->failedLogin($request);
        }

        if ($user->isSuspended()) {
            throw ValidationException::withMessages([
                'email' => ['A sua conta está suspensa. Contacte o administrador do sistema.'],
            ]);
        }

        if ($user->isLocked()) {
            throw ValidationException::withMessages([
                'email' => ['A sua conta está temporariamente bloqueada. Tente novamente mais tarde.'],
            ]);
        }

        if (! Hash::check($request->password, $user->password)) {
            $user->recordFailedAttempt();
            $this->failedLogin($request);
        }

        // Com 2FA ativo, devolve um desafio cifrado (substitui a sessão do Blade).
        if ($user->two_factor_enabled) {
            return response()->json([
                'two_factor' => true,
                'challenge' => Crypt::encryptString(json_encode([
                    'id' => $user->id,
                    'remember' => $request->boolean('remember'),
                    'expires' => now()->addMinutes(config('super_admin.two_factor_challenge_minutes', 10))->timestamp,
                ])),
            ]);
        }

        return $this->completeLogin($user, $request, $request->boolean('remember'));
    }

    /** POST /api/super-admin/auth/two-factor — código da app autenticadora ou código de recuperação. */
    public function verifyTwoFactor(Request $request): JsonResponse
    {
        $request->validate([
            'challenge' => 'required|string',
            'code' => 'required|string|max:20',
        ]);

        try {
            $payload = json_decode(Crypt::decryptString($request->challenge), true);
        } catch (DecryptException) {
            $payload = null;
        }

        if (! is_array($payload) || ($payload['expires'] ?? 0) < now()->timestamp) {
            return response()->json([
                'message' => 'A verificação expirou. Inicie sessão novamente.',
                'expired' => true,
            ], 422);
        }

        $user = SuperAdminUser::find($payload['id'] ?? null);

        if (! $user || ! $user->two_factor_enabled || $user->isSuspended() || $user->isLocked()) {
            return response()->json([
                'message' => 'Não foi possível concluir a verificação. Inicie sessão novamente.',
                'expired' => true,
            ], 422);
        }

        $code = trim($request->code);
        $valid = preg_match('/^\d{6}$/', $code)
            && (new Google2FA())->verifyKey((string) $user->two_factor_secret, $code, 1);

        if (! $valid && ! $this->useRecoveryCode($user, $code)) {
            $user->recordFailedAttempt();

            throw ValidationException::withMessages([
                'code' => ['O código fornecido é inválido.'],
            ]);
        }

        return $this->completeLogin($user, $request, (bool) ($payload['remember'] ?? false));
    }

    /** GET /api/super-admin/auth/me */
    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $request->user('super_admin')->toApiArray()]);
    }

    /** POST /api/super-admin/auth/logout */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user('super_admin');
        $token = $user->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        SuperAdminAuditService::log(
            event: 'logout',
            entityType: SuperAdminUser::class,
            entityId: $user->id,
            description: "Logout: {$user->email}",
            userId: $user->id,
        );

        return response()->json(['message' => 'Sessão terminada.']);
    }

    /** POST /api/super-admin/auth/forgot-password */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);

        $response = ['message' => 'Se o email estiver registado, receberá um link de recuperação.'];

        $user = SuperAdminUser::where('email', $request->email)->first();

        if ($user) {
            SuperAdminAuditService::log(
                event: 'password_reset_requested',
                entityType: SuperAdminUser::class,
                entityId: $user->id,
                description: "Pedido de recuperação de password: {$user->email}",
                userId: $user->id,
            );

            $token = Str::random(64);

            DB::table('super_admin_password_reset_tokens')->updateOrInsert(
                ['email' => $user->email],
                ['token' => Hash::make($token), 'created_at' => now()]
            );

            $url = frontend_url('/super-admin/reset-password/'.$token.'?email='.urlencode($user->email));
            $minutes = (int) config('super_admin.password_reset_minutes', 30);

            try {
                $user->notify(new SuperAdminResetPassword($url, $minutes));
            } catch (\Throwable $e) {
                Log::error('Falha ao enviar email de recuperação (Super Admin)', ['error' => $e->getMessage()]);
            }

            // Em desenvolvimento o link é devolvido para testes (como o dev_code do SMP).
            if (app()->environment('local', 'development')) {
                $response['dev_reset_url'] = $url;
            }
        }

        return response()->json($response);
    }

    /** POST /api/super-admin/auth/reset-password */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string',
            'email' => 'required|email',
            'password' => ['required', 'confirmed', PasswordRule::min(12)->mixedCase()->numbers()->symbols()],
        ]);

        $record = DB::table('super_admin_password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        if (! $record || ! Hash::check($request->token, $record->token)) {
            throw ValidationException::withMessages([
                'email' => ['O token de recuperação é inválido ou expirou.'],
            ]);
        }

        $minutes = (int) config('super_admin.password_reset_minutes', 30);
        if (! $record->created_at || Carbon::parse($record->created_at)->addMinutes($minutes)->isPast()) {
            DB::table('super_admin_password_reset_tokens')->where('email', $request->email)->delete();

            throw ValidationException::withMessages([
                'email' => ['O token de recuperação expirou. Solicite um novo.'],
            ]);
        }

        $user = SuperAdminUser::where('email', $request->email)->first();

        if (! $user) {
            throw ValidationException::withMessages(['email' => ['Utilizador não encontrado.']]);
        }

        $user->update([
            'password' => $request->password,
            'failed_attempts' => 0,
            'locked_until' => null,
        ]);

        // Termina todas as sessões abertas com a password antiga.
        $user->tokens()->delete();

        DB::table('super_admin_password_reset_tokens')->where('email', $request->email)->delete();

        SuperAdminAuditService::log(
            event: 'password_reset_completed',
            entityType: SuperAdminUser::class,
            entityId: $user->id,
            description: "Password redefinida via token: {$user->email}",
            userId: $user->id,
        );

        return response()->json(['message' => 'Password redefinida com sucesso. Pode agora iniciar sessão.']);
    }

    private function completeLogin(SuperAdminUser $user, Request $request, bool $remember): JsonResponse
    {
        $user->recordLogin($request->ip());

        $expiresAt = $remember
            ? now()->addDays(config('super_admin.remember_days', 30))
            : now()->addHours(config('super_admin.token_hours', 12));

        $token = $user->createToken('super-admin', ['super-admin'], $expiresAt)->plainTextToken;

        SuperAdminAuditService::log(
            event: 'login_successful',
            entityType: SuperAdminUser::class,
            entityId: $user->id,
            description: "Login bem-sucedido: {$user->email}",
            userId: $user->id,
        );

        return response()->json([
            'token' => $token,
            'expires_at' => $expiresAt->toIso8601String(),
            'user' => $user->fresh()->toApiArray(),
        ]);
    }

    /** Regista a tentativa falhada e responde com o erro de credenciais (lança exceção). */
    private function failedLogin(Request $request): never
    {
        $email = $request->email ?? 'unknown';

        SuperAdminAuditService::log(
            event: 'login_failed',
            entityType: SuperAdminUser::class,
            description: "Tentativa de login falhada para: {$email}",
            newValues: ['email' => $email],
        );

        throw ValidationException::withMessages([
            'email' => ['As credenciais fornecidas estão incorretas.'],
        ]);
    }

    private function useRecoveryCode(SuperAdminUser $user, string $code): bool
    {
        $codes = $user->two_factor_recovery_codes ?? [];
        $normalized = strtoupper($code);

        foreach ($codes as $index => $hashed) {
            if (Hash::check($normalized, $hashed)) {
                unset($codes[$index]);
                $user->update(['two_factor_recovery_codes' => array_values($codes)]);

                SuperAdminAuditService::log(
                    event: 'recovery_code_used',
                    entityType: SuperAdminUser::class,
                    entityId: $user->id,
                    description: "Código de recuperação utilizado: {$user->email}",
                    userId: $user->id,
                );

                return true;
            }
        }

        return false;
    }
}
