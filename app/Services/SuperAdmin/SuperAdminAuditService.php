<?php

namespace App\Services\SuperAdmin;

use App\Models\SuperAdminAuditLog;
use Illuminate\Http\Request;

/**
 * Registo de auditoria do painel Super Admin (tabela super_admin_audit_logs).
 * Separado do AuditService do SMP, que regista as ações dos utilizadores (tabela audit_logs).
 */
class SuperAdminAuditService
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
    ): SuperAdminAuditLog {
        $request = $request ?? request();

        return SuperAdminAuditLog::create([
            'super_admin_user_id' => $userId ?? auth()->guard('super_admin')->id(),
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
            if (in_array(strtolower((string) $key), self::SENSITIVE_KEYS, true)) {
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
