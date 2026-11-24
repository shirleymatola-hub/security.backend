<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class Phase57bAuthValidationTest extends TestCase
{
    private const PASSWORD = 'password123';

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('status')->default('active');
            $table->integer('failed_attempts')->default(0);
            $table->timestamp('locked_until')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->string('phone')->nullable();
            $table->string('locale', 5)->nullable();
            $table->string('theme')->nullable();
            $table->string('auth_type')->nullable();
            $table->string('google_id')->nullable();
            $table->string('avatar')->nullable();
            $table->string('profile_photo')->nullable();
            $table->string('address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_anonymous')->default(false);
            $table->unsignedBigInteger('police_station_id')->nullable();
            $table->string('agent_number')->nullable();
            $table->unsignedBigInteger('district_id')->nullable();
            $table->timestamp('email_verified_at')->nullable();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->string('code', 6)->nullable();
            $table->timestamp('created_at')->nullable();
        });

        foreach ([
            '2026_08_16_000001_create_audit_logs_table.php',
            '2026_10_04_123944_create_personal_access_tokens_table.php',
            '2026_06_30_150842_create_permission_tables.php',
        ] as $migration) {
            (include database_path("migrations/{$migration}"))->up();
        }

        app('cache')->forget(config('permission.cache.key'));
        Role::findOrCreate('citizen', 'web');
        Role::findOrCreate('citizen', 'sanctum');
        app('cache')->forget(config('permission.cache.key'));
    }

    public function test_required_email_returns_field_error_in_portuguese(): void
    {
        $response = $this->postJson('/api/auth/login', [], ['X-Locale' => 'pt']);

        $response->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['email', 'password']])
            ->assertJsonPath('message', 'Este campo é obrigatório. (e mais 1 erro)')
            ->assertJsonPath('errors.email.0', 'Este campo é obrigatório.')
            ->assertJsonPath('errors.password.0', 'Este campo é obrigatório.');

        $this->assertNoTechnicalKeys($response);
    }

    public function test_invalid_email_returns_portuguese_message(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'email-invalido',
            'password' => self::PASSWORD,
        ], ['X-Locale' => 'pt']);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('email')
            ->assertJsonPath('errors.email.0', 'Introduza um endereço de e-mail válido.');

        $this->assertNoTechnicalKeys($response);
    }

    public function test_duplicate_email_returns_portuguese_message(): void
    {
        $payload = $this->registerPayload();

        $this->postJson('/api/auth/register', $payload, ['X-Locale' => 'pt'])->assertCreated();

        $response = $this->postJson('/api/auth/register', $payload, ['X-Locale' => 'pt']);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('email')
            ->assertJsonPath('errors.email.0', 'Este endereço de e-mail já se encontra registado.');

        $this->assertNoTechnicalKeys($response);
    }

    public function test_short_password_and_confirmation_mismatch_are_reported_per_field(): void
    {
        $response = $this->postJson('/api/auth/register', $this->registerPayload([
            'password' => 'curta',
            'password_confirmation' => 'diferente',
        ]), ['X-Locale' => 'pt']);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('password')
            ->assertJsonFragment(['A palavra-passe deve conter, no mínimo, 8 caracteres.'])
            ->assertJsonFragment(['As palavras-passe não coincidem.']);

        $this->assertNoTechnicalKeys($response);
    }

    public function test_required_registration_fields_remain_associated_with_their_fields(): void
    {
        $response = $this->postJson('/api/auth/register', [], ['X-Locale' => 'pt']);

        $response->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['name', 'email', 'password']])
            ->assertJsonPath('errors.name.0', 'Este campo é obrigatório.')
            ->assertJsonPath('errors.email.0', 'Este campo é obrigatório.')
            ->assertJsonPath('errors.password.0', 'Este campo é obrigatório.');

        $this->assertNoTechnicalKeys($response);
    }

    public function test_english_locale_header_returns_english_messages(): void
    {
        $required = $this->postJson('/api/auth/register', [], ['X-Locale' => 'en']);

        $required->assertStatus(422)
            ->assertJsonPath('message', 'This field is required. (and 2 more errors)')
            ->assertJsonPath('errors.email.0', 'This field is required.');

        $invalid = $this->postJson('/api/auth/login', [
            'email' => 'email-invalido',
            'password' => self::PASSWORD,
        ], ['X-Locale' => 'en']);

        $invalid->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'Please enter a valid email address.');

        $this->assertNoTechnicalKeys($required);
        $this->assertNoTechnicalKeys($invalid);
    }

    public function test_accept_language_and_invalid_header_are_handled_safely(): void
    {
        $payload = ['email' => 'email-invalido', 'password' => self::PASSWORD];

        $english = $this->postJson('/api/auth/login', $payload, [
            'Accept-Language' => 'fr-FR,en;q=0.9,pt;q=0.8',
        ]);

        $english->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'Please enter a valid email address.');

        $portuguese = $this->postJson('/api/auth/login', $payload, [
            'X-Locale' => 'xx',
            'Accept-Language' => 'pt-PT,pt;q=0.9',
        ]);

        $portuguese->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'Introduza um endereço de e-mail válido.');
    }

    public function test_forgot_password_does_not_reveal_account_existence(): void
    {
        User::create([
            'name' => 'Cidadão Registado',
            'email' => 'registado@example.test',
            'password' => Hash::make(self::PASSWORD),
            'status' => 'active',
        ]);

        $existing = $this->postJson('/api/auth/forgot-password', [
            'email' => 'registado@example.test',
        ], ['X-Locale' => 'pt']);

        $missing = $this->postJson('/api/auth/forgot-password', [
            'email' => 'inexistente@example.test',
        ], ['X-Locale' => 'pt']);

        $message = 'Se existir uma conta associada a este endereço de e-mail, receberá as instruções de recuperação.';

        $existing->assertOk()->assertExactJson(['message' => $message]);
        $missing->assertOk()->assertExactJson(['message' => $message]);

        $this->assertDatabaseHas('password_reset_tokens', ['email' => 'registado@example.test']);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'inexistente@example.test']);
    }

    public function test_reset_password_does_not_disclose_missing_account(): void
    {
        DB::table('password_reset_tokens')->insert([
            'email' => 'semconta@example.test',
            'token' => Hash::make('token-valido'),
            'code' => '123456',
            'created_at' => now(),
        ]);

        $response = $this->postJson('/api/auth/reset-password', [
            'token' => 'token-valido',
            'email' => 'semconta@example.test',
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
        ], ['X-Locale' => 'en']);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('email')
            ->assertJsonPath('errors.email.0', 'The password could not be reset. Please request a new code.');

        $this->assertStringNotContainsString('Utilizador não encontrado.', json_encode($response->json(), JSON_UNESCAPED_UNICODE) ?: '');
    }

    public function test_login_rate_limiting_and_account_lockout_are_preserved(): void
    {
        $user = User::create([
            'name' => 'Cidadão',
            'email' => 'cidadao@example.test',
            'password' => Hash::make(self::PASSWORD),
            'status' => 'active',
        ]);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/auth/login', [
                'email' => $user->email,
                'password' => 'palavra-errada',
            ], ['X-Locale' => 'pt'])
                ->assertStatus(422)
                ->assertJsonPath('errors.email.0', 'Credenciais inválidas.');
        }

        $this->assertNotNull($user->fresh()->locked_until);

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'palavra-errada',
        ], ['X-Locale' => 'pt'])
            ->assertStatus(429)
            ->assertJsonPath('message', 'Demasiadas tentativas. Aguarde um momento e tente novamente.');
    }

    public function test_valid_login_still_issues_token(): void
    {
        User::create([
            'name' => 'Cidadão Valido',
            'email' => 'valido@example.test',
            'password' => Hash::make(self::PASSWORD),
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'valido@example.test',
            'password' => self::PASSWORD,
        ], ['X-Locale' => 'pt']);

        $response->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email']])
            ->assertJsonPath('user.email', 'valido@example.test');

        $this->withToken($response->json('token'))
            ->getJson('/api/auth/me', ['X-Locale' => 'pt'])
            ->assertOk()
            ->assertJsonPath('user.email', 'valido@example.test');
    }

    public function test_google_redirect_fails_safely_when_not_configured(): void
    {
        config()->set('services.google.client_id', null);
        config()->set('services.google.client_secret', null);
        config()->set('services.google.redirect', null);

        $response = $this->getJson('/api/auth/google/redirect', ['X-Locale' => 'en']);

        $response->assertStatus(503)
            ->assertExactJson(['message' => 'Google authentication is not configured.'])
            ->assertHeaderMissing('Location');
    }

    public function test_google_callback_fails_safely_when_not_configured(): void
    {
        config()->set('services.google.client_id', null);
        config()->set('services.google.client_secret', null);
        config()->set('services.google.redirect', null);

        $response = $this->get('/api/auth/google/callback');

        $response->assertStatus(302);
        $this->assertStringContainsString('/login?error=', (string) $response->headers->get('Location'));
    }

    public function test_internal_errors_do_not_expose_technical_details(): void
    {
        Route::middleware('api')->get('/_phase57b_failure', function (): never {
            throw new \RuntimeException('segredo-interno-nao-deve-aparecer');
        });

        $response = $this->getJson('/_phase57b_failure', ['X-Locale' => 'en']);

        $response->assertStatus(500)
            ->assertExactJson(['message' => 'The operation could not be completed. Please try again.']);

        $this->assertStringNotContainsString('segredo-interno-nao-deve-aparecer', (string) $response->getContent());
        $this->assertStringNotContainsString('exception', (string) $response->getContent());
    }

    private function registerPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Novo Cidadão',
            'email' => 'novo@example.test',
            'phone' => '800000000',
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
        ], $overrides);
    }

    private function assertNoTechnicalKeys(TestResponse $response): void
    {
        $content = json_encode($response->json(), JSON_UNESCAPED_UNICODE);

        $this->assertIsString($content);
        $this->assertStringNotContainsString('validation.', $content);
    }
}
