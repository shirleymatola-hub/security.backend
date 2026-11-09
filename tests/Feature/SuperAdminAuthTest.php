<?php

namespace Tests\Feature;

use App\Models\SuperAdminAuditLog;
use App\Models\SuperAdminUser;
use App\Models\User;
use App\Notifications\SuperAdminResetPassword;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * Autenticação do painel Super Admin (/api/super-admin/auth/*).
 *
 * As migrations completas do SMP precisam de PostgreSQL + PostGIS, por isso este
 * teste cria em SQLite (phpunit.xml) apenas as tabelas de que precisa.
 */
class SuperAdminAuthTest extends TestCase
{
    private const PASSWORD = 'Str0ng!Passw0rd#';

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('status')->default('active');
            $table->integer('failed_attempts')->default(0);
            $table->timestamp('locked_until')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        foreach ([
            '2026_08_16_000001_create_audit_logs_table.php',
            '2026_10_04_123944_create_personal_access_tokens_table.php',
            '2026_11_08_000001_create_super_admin_users_table.php',
            '2026_11_08_000002_create_super_admin_audit_logs_table.php',
        ] as $migration) {
            (include database_path("migrations/{$migration}"))->up();
        }
    }

    private function admin(array $attributes = []): SuperAdminUser
    {
        return SuperAdminUser::create(array_merge([
            'name' => 'Super Admin',
            'email' => 'superadmin@smp.gov',
            'password' => self::PASSWORD,
            'status' => 'active',
        ], $attributes));
    }

    private function login(string $password = self::PASSWORD)
    {
        return $this->postJson('/api/super-admin/auth/login', [
            'email' => 'superadmin@smp.gov',
            'password' => $password,
        ]);
    }

    public function test_login_devolve_token_que_da_acesso_ao_painel(): void
    {
        $this->admin();

        $token = $this->login()->assertOk()->assertJsonPath('user.email', 'superadmin@smp.gov')->json('token');

        $this->withToken($token)->getJson('/api/super-admin/auth/me')
            ->assertOk()
            ->assertJsonPath('user.name', 'Super Admin')
            ->assertJsonMissingPath('user.two_factor_secret');

        $this->assertDatabaseHas('super_admin_audit_logs', ['event' => 'login_successful']);
    }

    public function test_password_errada_e_registada_e_bloqueia_ao_fim_de_5_tentativas(): void
    {
        $admin = $this->admin();

        for ($i = 0; $i < 5; $i++) {
            $this->login('errada')->assertStatus(422)->assertJsonValidationErrors('email');
        }

        $this->assertTrue($admin->fresh()->isLocked());
        $this->assertSame(5, SuperAdminAuditLog::where('event', 'login_failed')->count());

        // 6.º pedido no mesmo minuto: limite de pedidos (5/min por IP).
        $this->login()->assertStatus(429);

        // Sem o limite de pedidos, a conta continua bloqueada mesmo com a password certa.
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        $this->login()->assertStatus(422)->assertJsonValidationErrors('email');
        $this->assertDatabaseHas('super_admin_audit_logs', ['event' => 'account_locked']);
    }

    public function test_tokens_do_smp_e_do_super_admin_nao_se_misturam(): void
    {
        $this->admin();
        $saToken = $this->login()->json('token');

        $user = User::forceCreate(['name' => 'Agente', 'email' => 'agente@smp.gov', 'password' => Hash::make('x')]);
        $smpToken = $user->createToken('smp-spa')->plainTextToken;

        // Token do super admin não serve nas rotas do SMP...
        $this->withToken($saToken)->getJson('/api/auth/me')->assertUnauthorized();

        // ...e o token de um utilizador do SMP não serve no painel.
        $this->app['auth']->forgetGuards();
        $this->withToken($smpToken)->getJson('/api/super-admin/auth/me')->assertUnauthorized();
    }

    public function test_login_com_2fa_exige_codigo_da_app_ou_de_recuperacao(): void
    {
        $google2fa = new Google2FA();
        $secret = $google2fa->generateSecretKey();
        $this->admin([
            'two_factor_enabled' => true,
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => [Hash::make('ABCD-EFGH')],
        ]);

        $challenge = $this->login()->assertOk()->assertJsonPath('two_factor', true)->assertJsonMissingPath('token')->json('challenge');

        $this->postJson('/api/super-admin/auth/two-factor', ['challenge' => $challenge, 'code' => '000000'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('code');

        $this->postJson('/api/super-admin/auth/two-factor', ['challenge' => $challenge, 'code' => $google2fa->getCurrentOtp($secret)])
            ->assertOk()
            ->assertJsonStructure(['token', 'user']);

        // Código de recuperação (minúsculas aceites) só funciona uma vez.
        $this->postJson('/api/super-admin/auth/two-factor', ['challenge' => $challenge, 'code' => 'abcd-efgh'])->assertOk();
        $this->postJson('/api/super-admin/auth/two-factor', ['challenge' => $challenge, 'code' => 'ABCD-EFGH'])->assertStatus(422);

        $this->postJson('/api/super-admin/auth/two-factor', ['challenge' => 'invalido', 'code' => '123456'])
            ->assertStatus(422)
            ->assertJsonPath('expired', true);
    }

    public function test_conta_suspensa_perde_a_sessao(): void
    {
        $admin = $this->admin();
        $token = $this->login()->json('token');

        $admin->update(['status' => 'suspended']);

        $this->withToken($token)->getJson('/api/super-admin/dashboard')->assertUnauthorized();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_logout_revoga_o_token(): void
    {
        $this->admin();
        $token = $this->login()->json('token');

        $this->withToken($token)->postJson('/api/super-admin/auth/logout')->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertDatabaseHas('super_admin_audit_logs', ['event' => 'logout']);
    }

    public function test_recuperacao_de_password(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $oldToken = $this->login()->json('token');

        $this->postJson('/api/super-admin/auth/forgot-password', ['email' => 'superadmin@smp.gov'])->assertOk();
        Notification::assertSentTo($admin, SuperAdminResetPassword::class);

        // Email desconhecido: mesma resposta, sem revelar se a conta existe.
        $this->postJson('/api/super-admin/auth/forgot-password', ['email' => 'nao@existe.mz'])->assertOk();

        DB::table('super_admin_password_reset_tokens')->where('email', $admin->email)->update(['token' => Hash::make('token-de-teste')]);

        $payload = [
            'token' => 'token-de-teste',
            'email' => $admin->email,
            'password' => 'Nova!Passw0rd#2026',
            'password_confirmation' => 'Nova!Passw0rd#2026',
        ];

        $this->postJson('/api/super-admin/auth/reset-password', ['password' => 'fraca', 'password_confirmation' => 'fraca'] + $payload)
            ->assertJsonValidationErrors('password');

        $this->postJson('/api/super-admin/auth/reset-password', $payload)->assertOk();

        $this->assertTrue(Hash::check('Nova!Passw0rd#2026', $admin->fresh()->password));
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $admin->id, 'token' => hash('sha256', explode('|', $oldToken)[1])]);
        $this->assertDatabaseCount('super_admin_password_reset_tokens', 0);
    }

    public function test_link_de_recuperacao_expira(): void
    {
        $admin = $this->admin();
        DB::table('super_admin_password_reset_tokens')->insert([
            'email' => $admin->email,
            'token' => Hash::make('antigo'),
            'created_at' => now()->subMinutes(31),
        ]);

        $this->postJson('/api/super-admin/auth/reset-password', [
            'token' => 'antigo',
            'email' => $admin->email,
            'password' => 'Nova!Passw0rd#2026',
            'password_confirmation' => 'Nova!Passw0rd#2026',
        ])->assertJsonValidationErrors('email');
    }

    public function test_alterar_password_e_perfil(): void
    {
        $this->admin();
        $token = $this->login()->json('token');

        $this->withToken($token)->putJson('/api/super-admin/profile/password', [
            'current_password' => 'errada',
            'password' => 'Nova!Passw0rd#2026',
            'password_confirmation' => 'Nova!Passw0rd#2026',
        ])->assertJsonValidationErrors('current_password');

        $this->withToken($token)->putJson('/api/super-admin/profile/password', [
            'current_password' => self::PASSWORD,
            'password' => 'Nova!Passw0rd#2026',
            'password_confirmation' => 'Nova!Passw0rd#2026',
        ])->assertOk();

        $this->withToken($token)->putJson('/api/super-admin/profile', ['name' => 'Novo Nome', 'email' => 'novo@smp.gov'])
            ->assertOk()
            ->assertJsonPath('user.name', 'Novo Nome');

        $this->assertDatabaseHas('super_admin_audit_logs', ['event' => 'password_changed']);
        $this->assertDatabaseHas('super_admin_audit_logs', ['event' => 'profile_updated']);
        // Logins não aparecem como "perfil atualizado".
        $this->assertSame(1, SuperAdminAuditLog::where('event', 'profile_updated')->count());
    }

    public function test_ativar_2fa_com_qr_code(): void
    {
        $this->admin();
        $token = $this->login()->json('token');

        $setup = $this->withToken($token)->getJson('/api/super-admin/two-factor/setup')
            ->assertOk()
            ->assertJsonStructure(['qr_svg', 'secret', 'setup_token'])
            ->json();

        $this->assertStringContainsString('<svg', $setup['qr_svg']);

        $codes = $this->withToken($token)->postJson('/api/super-admin/two-factor/enable', [
            'password' => self::PASSWORD,
            'code' => (new Google2FA())->getCurrentOtp($setup['secret']),
            'setup_token' => $setup['setup_token'],
        ])->assertOk()->assertJsonPath('user.two_factor_enabled', true)->json('recovery_codes');

        $this->assertCount(8, $codes);
        $this->assertDatabaseHas('super_admin_audit_logs', ['event' => 'two_factor_enabled']);
        $this->withToken($token)->getJson('/api/super-admin/two-factor/setup')->assertStatus(422);
    }
}
