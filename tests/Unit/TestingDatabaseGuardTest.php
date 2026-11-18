<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tests\Support\TestingDatabaseGuard;

class TestingDatabaseGuardTest extends TestCase
{
    public function test_only_in_memory_sqlite_is_disposable(): void
    {
        $this->assertTrue(TestingDatabaseGuard::isDisposable([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]));
        $this->assertTrue(TestingDatabaseGuard::isDisposable([
            'driver' => 'sqlite',
            'database' => 'file::memory:?cache=shared',
        ]));

        $this->assertFalse(TestingDatabaseGuard::isDisposable([
            'driver' => 'pgsql',
            'database' => 'smp_test',
        ]));
        $this->assertFalse(TestingDatabaseGuard::isDisposable([
            'driver' => 'pgsql',
            'database' => 'smp',
        ]));
        $this->assertFalse(TestingDatabaseGuard::isDisposable([
            'driver' => 'pgsql',
            'database' => 'super_admin_db',
        ]));
        $this->assertFalse(TestingDatabaseGuard::isDisposable([
            'driver' => 'sqlite',
            'database' => 'D:/security/security.backend-atualizado/database/database.sqlite',
        ]));
    }

    public function test_read_only_statements_are_allowed(): void
    {
        $this->assertTrue(TestingDatabaseGuard::isReadOnly('SELECT 1'));
        $this->assertTrue(TestingDatabaseGuard::isReadOnly('  with x as (select 1) select * from x'));
        $this->assertTrue(TestingDatabaseGuard::isReadOnly('EXPLAIN ANALYZE SELECT 1'));
        $this->assertTrue(TestingDatabaseGuard::isReadOnly('SHOW search_path'));
    }

    public function test_destructive_and_write_statements_are_blocked(): void
    {
        $this->assertFalse(TestingDatabaseGuard::isReadOnly('DROP TABLE incidents'));
        $this->assertFalse(TestingDatabaseGuard::isReadOnly('TRUNCATE incidents'));
        $this->assertFalse(TestingDatabaseGuard::isReadOnly('DELETE FROM incidents'));
        $this->assertFalse(TestingDatabaseGuard::isReadOnly('INSERT INTO incidents (id) VALUES (1)'));
        $this->assertFalse(TestingDatabaseGuard::isReadOnly('UPDATE incidents SET is_public = true'));
        $this->assertFalse(TestingDatabaseGuard::isReadOnly('ALTER TABLE incidents ADD COLUMN x int'));
        $this->assertFalse(TestingDatabaseGuard::isReadOnly('CREATE TABLE x (id int)'));
        $this->assertFalse(TestingDatabaseGuard::isReadOnly('WITH x AS (DELETE FROM incidents RETURNING id) SELECT * FROM x'));
        $this->assertFalse(TestingDatabaseGuard::isReadOnly('SELECT 1; DROP TABLE incidents'));
    }

    public function test_guard_raises_instead_of_running_a_destructive_statement(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Operação bloqueada pela guarda de testes');

        TestingDatabaseGuard::assertReadOnly('pgsql', 'DROP TABLE incidents');
    }
}
