<?php

namespace Tests\Unit;

use App\Models\Incident;
use App\Services\AreaAtuacaoService;
use App\Services\IncidentJurisdictionService;
use App\Services\IncidentLocationService;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class IncidentLocationCorrectionTest extends TestCase
{
    public function test_clears_trigger_assignment_even_when_model_value_is_null(): void
    {
        $query = Mockery::mock(Builder::class);
        $query->shouldReceive('where')->once()->with('id', 123)->andReturnSelf();
        $query->shouldReceive('update')->once()->with(['police_station_id' => null])->andReturn(1);

        DB::shouldReceive('table')->once()->with('incidents')->andReturn($query);

        $incident = new Incident();
        $incident->forceFill(['id' => 123, 'police_station_id' => null]);
        $incident->syncOriginal();

        $this->makeService()->ensureNoStationForUnresolved($incident, ['police_station_id' => null]);

        $this->assertNull($incident->police_station_id);
        $this->assertFalse($incident->isDirty('police_station_id'));
    }

    public function test_does_not_clear_a_territorially_resolved_station(): void
    {
        DB::shouldReceive('table')->never();

        $incident = new Incident();
        $incident->forceFill(['id' => 124, 'police_station_id' => 2]);
        $incident->syncOriginal();

        $this->makeService()->ensureNoStationForUnresolved($incident, ['police_station_id' => 2]);

        $this->assertSame(2, $incident->police_station_id);
    }

    private function makeService(): IncidentLocationService
    {
        return new IncidentLocationService(
            $this->createMock(IncidentJurisdictionService::class),
            $this->createMock(AreaAtuacaoService::class)
        );
    }
}
