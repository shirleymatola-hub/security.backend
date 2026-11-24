<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class PoliceJurisdictionSpatialFilter
{
    public static function apply(Builder $query, int $policeStationId, string $table = 'incidents'): Builder
    {
        return $query->whereRaw(self::existsCondition($table), [$policeStationId]);
    }

    public static function existsCondition(string $table = 'incidents'): string
    {
        $point = self::pointExpression($table);

        return '
            EXISTS (
                SELECT 1
                FROM "Area de Actuacao" aa
                JOIN "Area de Estudo" ae
                  ON ae.geom IS NOT NULL
                 AND ST_IsValid(ae.geom)
                 AND ST_SRID(ae.geom) > 0
                 AND ST_Covers(ST_Transform(ae.geom, 4326), '.$point.')
                JOIN jurisdictions j
                  ON j.area_estudo_id = ae.id
                 AND j.is_active = true
                 AND j.police_station_id = aa.police_station_id
                WHERE aa.police_station_id = ?
                  AND aa.geom IS NOT NULL
                  AND ST_IsValid(aa.geom)
                  AND ST_SRID(aa.geom) > 0
                  AND ST_Covers(ST_Transform(aa.geom, 4326), '.$point.')
            )
        ';
    }

    public static function stationIdForJurisdiction(int $jurisdictionId): ?int
    {
        $stationId = DB::table('jurisdictions')
            ->where('id', $jurisdictionId)
            ->where('is_active', true)
            ->value('police_station_id');

        return $stationId === null ? null : (int) $stationId;
    }

    private static function pointExpression(string $table): string
    {
        $latitude = self::qualified($table, 'latitude');
        $longitude = self::qualified($table, 'longitude');

        return 'ST_SetSRID(ST_MakePoint('.$longitude.'::double precision, '.$latitude.'::double precision), 4326)';
    }

    private static function qualified(string $table, string $column): string
    {
        return '"'.str_replace('"', '""', $table).'"."'.str_replace('"', '""', $column).'"';
    }
}
