<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\SuperAdminUser;
use App\Services\SuperAdmin\SuperAdminAuditService;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

/**
 * Ativação/desativação da autenticação em duas etapas (Google Authenticator, Authy...).
 * O segredo provisório viaja num "setup_token" cifrado em vez da sessão.
 */
class TwoFactorController extends Controller
{
    /** GET /api/super-admin/two-factor/setup — QR code e segredo para a app. */
    public function setup(Request $request): JsonResponse
    {
        $user = $request->user('super_admin');

        if ($user->two_factor_enabled) {
            return response()->json([
                'message' => 'A autenticação em duas etapas já está activada.',
                'enabled' => true,
            ], 422);
        }

        $google2fa = new Google2FA();
        $secret = $google2fa->generateSecretKey();

        $qrUrl = $google2fa->getQRCodeUrl(config('app.name').' Super Admin', $user->email, $secret);
        $svg = (new Writer(new ImageRenderer(new RendererStyle(200), new SvgImageBackEnd())))->writeString($qrUrl);

        return response()->json([
            'qr_svg' => $svg,
            'secret' => $secret,
            'setup_token' => Crypt::encryptString(json_encode([
                'id' => $user->id,
                'secret' => $secret,
                'expires' => now()->addMinutes(15)->timestamp,
            ])),
        ]);
    }

    /** POST /api/super-admin/two-factor/enable */
    public function enable(Request $request): JsonResponse
    {
        $user = $request->user('super_admin');

        $request->validate([
            'password' => 'required',
            'code' => 'required|string|size:6',
            'setup_token' => 'required|string',
        ]);

        $this->checkPassword($user, $request->password);

        try {
            $payload = json_decode(Crypt::decryptString($request->setup_token), true);
        } catch (DecryptException) {
            $payload = null;
        }

        if (! is_array($payload) || ($payload['id'] ?? null) !== $user->id || ($payload['expires'] ?? 0) < now()->timestamp) {
            throw ValidationException::withMessages([
                'code' => ['Sessão expirada. Gere um novo código QR.'],
            ]);
        }

        if (! (new Google2FA())->verifyKey($payload['secret'], $request->code, 1)) {
            throw ValidationException::withMessages([
                'code' => ['O código fornecido é inválido.'],
            ]);
        }

        $codes = $this->generateRecoveryCodes();

        $user->update([
            'two_factor_enabled' => true,
            'two_factor_secret' => $payload['secret'],
            'two_factor_recovery_codes' => array_map(fn ($c) => Hash::make($c), $codes),
        ]);

        return response()->json([
            'message' => 'Autenticação de dois fatores activada com sucesso.',
            'recovery_codes' => $codes,
            'user' => $user->toApiArray(),
        ]);
    }

    /** POST /api/super-admin/two-factor/disable */
    public function disable(Request $request): JsonResponse
    {
        $user = $request->user('super_admin');

        $request->validate(['password' => 'required']);
        $this->checkPassword($user, $request->password);

        $user->update([
            'two_factor_enabled' => false,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
        ]);

        return response()->json([
            'message' => 'Autenticação de dois fatores desactivada.',
            'user' => $user->toApiArray(),
        ]);
    }

    /** POST /api/super-admin/two-factor/recovery-codes — gera novos códigos de recuperação. */
    public function regenerate(Request $request): JsonResponse
    {
        $user = $request->user('super_admin');

        $request->validate(['password' => 'required']);
        $this->checkPassword($user, $request->password);

        if (! $user->two_factor_enabled) {
            throw ValidationException::withMessages([
                'password' => ['A autenticação de dois fatores não está activada.'],
            ]);
        }

        $codes = $this->generateRecoveryCodes();

        $user->update([
            'two_factor_recovery_codes' => array_map(fn ($c) => Hash::make($c), $codes),
        ]);

        SuperAdminAuditService::log(
            event: 'recovery_codes_regenerated',
            entityType: SuperAdminUser::class,
            entityId: $user->id,
            description: "Códigos de recuperação regenerados: {$user->email}",
        );

        return response()->json([
            'message' => 'Códigos de recuperação regenerados com sucesso.',
            'recovery_codes' => $codes,
        ]);
    }

    private function checkPassword(SuperAdminUser $user, string $password): void
    {
        if (! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['A password está incorreta.'],
            ]);
        }
    }

    private function generateRecoveryCodes(): array
    {
        $codes = [];
        for ($i = 0; $i < 8; $i++) {
            $codes[] = strtoupper(Str::random(4).'-'.Str::random(4));
        }

        return $codes;
    }
}
