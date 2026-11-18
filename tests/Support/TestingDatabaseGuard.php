<?php

namespace Tests\Support;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Fail-closed guard for test database connections.
 *
 * Only in-memory SQLite connections are considered disposable. Every other
 * configured connection (including smp_test, smp, super_admin_db and any
 * production database) may only execute read-only statements while tests run.
 */
final class TestingDatabaseGuard
{
    private const READ_ONLY_STATEMENT = '/^\s*(select|with|show|explain|describe|desc)\b/i';

    private const WRITE_STATEMENT = '/(^|;)\s*(insert|update|delete|drop|truncate|alter|create|replace|grant|revoke|comment|copy|vacuum|reindex|cluster|refresh|call|do|merge|lock|setval|select\s+pg_terminate_backend)\b/i';

    private const CTE_WRITE_STATEMENT = '/\b(insert|update|delete|merge)\b/i';

    private static bool $registered = false;

    public static function register(): void
    {
        if (self::$registered) {
            return;
        }

        self::$registered = true;

        DB::connection()->listen(function (QueryExecuted $query): void {
            $config = config("database.connections.{$query->connectionName}", []);

            if (self::isDisposable($config)) {
                return;
            }

            self::assertReadOnly($query->connectionName, $query->sql, (string) ($config['database'] ?? 'desconhecida'));
        });
    }

    public static function assertReadOnly(string $connectionName, string $sql, string $database = 'desconhecida'): void
    {
        if (self::isReadOnly($sql)) {
            return;
        }

        throw new RuntimeException(sprintf(
            'Operação bloqueada pela guarda de testes: a ligação [%s] usa a base [%s], '
            . 'que não é descartável. Apenas leitura é permitida. SQL: %s',
            $connectionName,
            $database,
            self::summarize($sql)
        ));
    }

    public static function isReadOnly(string $sql): bool
    {
        $sql = trim($sql);

        if ($sql === '') {
            return true;
        }

        if (! preg_match(self::READ_ONLY_STATEMENT, $sql)) {
            return false;
        }

        if (preg_match('/^\s*with\b/i', $sql) && preg_match(self::CTE_WRITE_STATEMENT, $sql)) {
            return false;
        }

        return ! preg_match(self::WRITE_STATEMENT, $sql);
    }

    public static function isDisposable(array $config): bool
    {
        $database = (string) ($config['database'] ?? '');

        if ($database === ':memory:' || str_contains($database, 'file::memory:')) {
            return true;
        }

        return ($config['driver'] ?? null) === 'sqlite'
            && str_starts_with($database, sys_get_temp_dir())
            && str_contains(basename($database), 'test');
    }

    private static function summarize(string $sql): string
    {
        $sql = preg_replace('/\s+/', ' ', trim($sql)) ?? $sql;

        return strlen($sql) > 180 ? substr($sql, 0, 177) . '...' : $sql;
    }
}
