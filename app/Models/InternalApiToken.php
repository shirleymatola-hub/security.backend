<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class InternalApiToken extends Model
{
    protected $fillable = [
        'token_name',
        'token_hash',
        'abilities',
        'last_used_at',
        'expires_at',
    ];

    protected $hidden = [
        'token_hash',
    ];

    protected function casts(): array
    {
        return [
            'abilities' => 'array',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function hasAbility(string $ability): bool
    {
        return in_array('*', $this->abilities ?? []) || in_array($ability, $this->abilities ?? []);
    }

    public function recordUsage(): void
    {
        $this->update(['last_used_at' => now()]);
    }

    public static function createFromPlainText(string $name, array $abilities, ?string $expiresAt = null): array
    {
        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);

        $record = static::create([
            'token_name' => $name,
            'token_hash' => $hash,
            'abilities' => $abilities,
            'expires_at' => $expiresAt,
        ]);

        return ['token' => $token, 'record' => $record];
    }
}
