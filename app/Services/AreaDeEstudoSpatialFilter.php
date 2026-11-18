<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;

class AreaDeEstudoSpatialFilter
{
    public static function apply(
        Builder $query,
        string $geometryColumn = 'geometry',
        string $latitudeColumn = 'latitude',
        string $longitudeColumn = 'longitude'
    ): Builder {
        $point = self::pointExpression($query, $geometryColumn, $latitudeColumn, $longitudeColumn);

        return $query->whereRaw(
            'EXISTS (SELECT 1 FROM "Area de Estudo" ae '
            . 'WHERE ae.geom IS NOT NULL AND ST_IsValid(ae.geom) AND ST_SRID(ae.geom) > 0 '
            . "AND ST_Covers(ST_Transform(ae.geom, 4326), {$point}))"
        );
    }

    public static function applyForArea(
        Builder $query,
        int $areaId,
        string $geometryColumn = 'geometry',
        string $latitudeColumn = 'latitude',
        string $longitudeColumn = 'longitude'
    ): Builder {
        $point = self::pointExpression($query, $geometryColumn, $latitudeColumn, $longitudeColumn);

        return $query->whereRaw(
            'EXISTS (SELECT 1 FROM "Area de Estudo" ae '
            . 'WHERE ae.geom IS NOT NULL AND ST_IsValid(ae.geom) AND ST_SRID(ae.geom) > 0 '
            . 'AND TRIM(ae."Bairro") = (SELECT TRIM(aex."Bairro") FROM "Area de Estudo" aex WHERE aex.id = ?) '
            . "AND ST_Covers(ST_Transform(ae.geom, 4326), {$point}))",
            [$areaId]
        );
    }

    private static function pointExpression(
        Builder $query,
        string $geometryColumn,
        string $latitudeColumn,
        string $longitudeColumn
    ): string {
        $table = $query->getModel()->getTable();
        $geometry = self::qualified($table, $geometryColumn);
        $latitude = self::qualified($table, $latitudeColumn);
        $longitude = self::qualified($table, $longitudeColumn);

        return '(CASE '
            . "WHEN {$latitude} IS NOT NULL AND {$longitude} IS NOT NULL "
            . "AND {$latitude} BETWEEN -90 AND 90 AND {$longitude} BETWEEN -180 AND 180 "
            . "THEN ST_SetSRID(ST_MakePoint({$longitude}, {$latitude}), 4326) "
            . "WHEN {$geometry} IS NOT NULL AND ST_IsValid({$geometry}) AND ST_SRID({$geometry}) > 0 "
            . "THEN ST_Transform({$geometry}, 4326) "
            . 'END)';
    }

    private static function qualified(string $table, string $column): string
    {
        return '"' . str_replace('"', '""', $table) . '"."' . str_replace('"', '""', $column) . '"';
    }
}
