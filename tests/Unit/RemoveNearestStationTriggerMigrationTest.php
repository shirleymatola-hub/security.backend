<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class RemoveNearestStationTriggerMigrationTest extends TestCase
{
    private static ?object $migration = null;

    public function test_up_removes_only_the_nearest_station_trigger(): void
    {
        DB::shouldReceive('statement')
            ->once()
            ->with('DROP TRIGGER IF EXISTS trigger_assign_nearest_station ON public.incidents');

        $this->migration()->up();
    }

    public function test_down_recreates_the_trigger_using_the_preserved_function(): void
    {
        DB::shouldReceive('statement')
            ->once()
            ->with(Mockery::pattern('/CREATE TRIGGER trigger_assign_nearest_station[\s\S]*EXECUTE FUNCTION assign_nearest_station\(\)/'));

        $this->migration()->down();
    }

    private function migration(): object
    {
        return self::$migration ??= require database_path(
            'migrations/2026_11_19_000001_remove_trigger_assign_nearest_station.php'
        );
    }
}
