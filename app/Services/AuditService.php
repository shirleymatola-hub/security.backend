<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditService
{
    private const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'current_password',
        'token',
        'token_hash',
        'secret',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'api_token',
        'remember_token',
        'email_verification_token',
    ];

    public static function log(
        string $event,
        ?string $entityType = null,
        ?int $entityId = null,
        ?string $description = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?int $userId = null,
        ?Request $request = null,
    ): AuditLog {
        $request = $request ?? request();

        return AuditLog::create([
            'user_id' => $userId ?? auth()->id(),
            'event' => $event,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'description' => $description,
            'old_values' => self::filterSensitive($oldValues),
            'new_values' => self::filterSensitive($newValues),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }

    private static function filterSensitive(?array $data): ?array
    {
        if (empty($data)) {
            return null;
        }

        $filtered = [];
        foreach ($data as $key => $value) {
            $lowerKey = strtolower($key);
            if (in_array($lowerKey, self::SENSITIVE_KEYS, true)) {
                $filtered[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $filtered[$key] = self::filterSensitive($value) ?? [];
            } else {
                $filtered[$key] = $value;
            }
        }

        return $filtered;
    }
}
