<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InternalUserInvitation extends Model
{
    protected $fillable = [
        'email',
        'name',
        'token_hash',
        'role',
        'police_station_id',
        'district_id',
        'invited_by',
        'expires_at',
        'accepted_at',
        'status',
    ];

    protected $hidden = [
        'token_hash',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    public function policeStation(): BelongsTo
    {
        return $this->belongsTo(PoliceStation::class);
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isAccepted(): bool
    {
        return $this->status === 'accepted';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function canBeAccepted(): bool
    {
        return $this->isPending() && !$this->isExpired();
    }

    public static function createFromPlainText(string $token, array $data): static
    {
        $hash = hash('sha256', $token);

        return static::create(array_merge($data, [
            'token_hash' => $hash,
        ]));
    }

    public static function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }
}
