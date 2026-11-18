<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;

class AreaAtuacaoSpatialFilter
{
    public static function apply(
        Builder $query,
        int $areaId,
        string $geometryColumn = 'geometry',
        string $latitudeColumn = 'latitude',
        string $longitudeColumn = 'longitude'
    ): Builder {
        $table = $query->getModel()->getTable();
        $geometry = self::qualified($table, $geometryColumn);
        $latitude = self::qualified($table, $latitudeColumn);
        $longitude = self::qualified($table, $longitudeColumn);

        $point = '(CASE '
            ."WHEN {$latitude} IS NOT NULL AND {$longitude} IS NOT NULL "
            ."AND {$latitude} BETWEEN -90 AND 90 AND {$longitude} BETWEEN -180 AND 180 "
            ."THEN ST_SetSRID(ST_MakePoint({$longitude}::double precision, {$latitude}::double precision), 4326) "
            ."WHEN {$geometry} IS NOT NULL AND ST_IsValid({$geometry}) AND ST_SRID({$geometry}) > 0 "
            ."THEN ST_Transform({$geometry}, 4326) "
            .'END)';

        return $query->whereRaw(
            'EXISTS (SELECT 1 FROM "Area de Actuacao" aa '
            .'WHERE aa.id = ? AND aa.geom IS NOT NULL AND ST_IsValid(aa.geom) AND ST_SRID(aa.geom) > 0 '
            .'AND ST_Covers('
            .self::transformedGeometry()
            .", {$point}))",
            [$areaId]
        );
    }

    public static function transformedGeometry(string $alias = 'aa.geom'): string
    {
        return "(CASE WHEN ST_SRID({$alias}) = 4326 THEN {$alias} ELSE ST_Transform({$alias}, 4326) END)";
    }

    private static function qualified(string $table, string $column): string
    {
        return '"'.str_replace('"', '""', $table).'"."'.str_replace('"', '""', $column).'"';
    }
}
