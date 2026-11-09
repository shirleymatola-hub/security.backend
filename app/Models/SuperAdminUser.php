<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Conta do painel Super Admin. É independente dos utilizadores do SMP (User):
 * os tokens destes utilizadores só são aceites pelo guard "super_admin"
 * (rotas /api/super-admin/*) e o guard "sanctum" do SMP não os aceita.
 */
class SuperAdminUser extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table = 'super_admin_users';

    protected $fillable = [
        'name',
        'email',
        'password',
        'two_factor_enabled',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'status',
        'last_login_at',
        'last_login_ip',
        'failed_attempts',
        'locked_until',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'two_factor_enabled' => 'boolean',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'array',
            'last_login_at' => 'datetime',
            'failed_attempts' => 'integer',
            'locked_until' => 'datetime',
        ];
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    public function isLocked(): bool
    {
        return $this->locked_until && $this->locked_until->isFuture();
    }

    public function recordLogin(string $ip): void
    {
        $this->update([
            'last_login_at' => now(),
            'last_login_ip' => $ip,
            'failed_attempts' => 0,
            'locked_until' => null,
        ]);
    }

    public function recordFailedAttempt(): void
    {
        $attempts = $this->failed_attempts + 1;

        $this->update([
            'failed_attempts' => $attempts,
            'locked_until' => $attempts >= 5 ? now()->addMinutes(15) : null,
        ]);
    }

    public function resetFailedAttempts(): void
    {
        $this->update([
            'failed_attempts' => 0,
            'locked_until' => null,
        ]);
    }

    /** Dados enviados ao frontend (nunca inclui segredos de 2FA). */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'status' => $this->status,
            'two_factor_enabled' => (bool) $this->two_factor_enabled,
            'last_login_at' => $this->last_login_at,
            'last_login_ip' => $this->last_login_ip,
            'created_at' => $this->created_at,
        ];
    }
}
