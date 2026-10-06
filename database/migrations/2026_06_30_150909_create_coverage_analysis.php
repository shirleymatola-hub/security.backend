<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Create buffer zones view for coverage analysis
        DB::statement("
            CREATE OR REPLACE VIEW police_station_coverage AS
            SELECT
                ps.id,
                ps.name,
                ps.code,
                ps.address,
                ps.phone,
                ps.district_id,
                ps.is_active,
                ST_Buffer(ps.geometry::geography, 1000)::geometry AS buffer_1km,
                ST_Buffer(ps.geometry::geography, 3000)::geometry AS buffer_3km,
                ST_Buffer(ps.geometry::geography, 5000)::geometry AS buffer_5km,
                (SELECT COUNT(*) FROM incidents i
                 WHERE i.geometry IS NOT NULL
                   AND ST_DWithin(i.geometry::geography, ps.geometry::geography, 1000)
                ) AS incidents_within_1km,
                (SELECT COUNT(*) FROM incidents i
                 WHERE i.geometry IS NOT NULL
                   AND ST_DWithin(i.geometry::geography, ps.geometry::geography, 3000)
                ) AS incidents_within_3km,
                (SELECT COUNT(*) FROM incidents i
                 WHERE i.geometry IS NOT NULL
                   AND ST_DWithin(i.geometry::geography, ps.geometry::geography, 5000)
                ) AS incidents_within_5km
            FROM police_stations ps
            WHERE ps.is_active = true
              AND ps.geometry IS NOT NULL
        ");

        // Create function to find incidents within coverage zone
        DB::statement("
            CREATE OR REPLACE FUNCTION get_incidents_in_coverage(
                station_id BIGINT,
                radius_meters INTEGER DEFAULT 3000
            )
            RETURNS TABLE (
                incident_id BIGINT,
                reference_code VARCHAR,
                title VARCHAR,
                status VARCHAR,
                priority VARCHAR,
                distance_meters NUMERIC
            ) AS $$
            BEGIN
                RETURN QUERY
                SELECT
                    i.id,
                    i.reference_code::VARCHAR,
                    i.title::VARCHAR,
                    i.status::VARCHAR,
                    i.priority::VARCHAR,
                    ROUND(
                        ST_Distance(
                            i.geometry::geography,
                            ps.geometry::geography
                        )::numeric, 2
                    ) AS distance_meters
                FROM incidents i
                CROSS JOIN police_stations ps
                WHERE ps.id = station_id
                  AND i.geometry IS NOT NULL
                  AND ps.geometry IS NOT NULL
                  AND ST_DWithin(
                      i.geometry::geography,
                      ps.geometry::geography,
                      radius_meters
                  )
                ORDER BY distance_meters;
            END;
            $$ LANGUAGE plpgsql
        ");
    }

    public function down(): void
    {
        DB::statement("DROP FUNCTION IF EXISTS get_incidents_in_coverage(BIGINT, INTEGER)");
        DB::statement("DROP VIEW IF EXISTS police_station_coverage");
    }
};
