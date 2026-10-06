<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Create function to calculate route distance using OSRM-compatible format
        DB::statement("
            CREATE OR REPLACE FUNCTION calculate_route_distance(
                origin_lat DECIMAL,
                origin_lng DECIMAL,
                dest_lat DECIMAL,
                dest_lng DECIMAL
            )
            RETURNS TABLE (
                distance_meters NUMERIC,
                duration_seconds NUMERIC,
                origin_point GEOMETRY,
                destination_point GEOMETRY
            ) AS $$
            BEGIN
                RETURN QUERY
                SELECT
                    ROUND(
                        ST_Distance(
                            ST_SetSRID(ST_MakePoint(origin_lng, origin_lat), 4326)::geography,
                            ST_SetSRID(ST_MakePoint(dest_lng, dest_lat), 4326)::geography
                        )::numeric, 2
                    ) AS distance_meters,
                    -- Approximate duration assuming 30 km/h average speed in urban area
                    ROUND(
                        (ST_Distance(
                            ST_SetSRID(ST_MakePoint(origin_lng, origin_lat), 4326)::geography,
                            ST_SetSRID(ST_MakePoint(dest_lng, dest_lat), 4326)::geography
                        ) / 30000.0 * 3600)::numeric, 2
                    ) AS duration_seconds,
                    ST_SetSRID(ST_MakePoint(origin_lng, origin_lat), 4326) AS origin_point,
                    ST_SetSRID(ST_MakePoint(dest_lng, dest_lat), 4326) AS destination_point;
            END;
            $$ LANGUAGE plpgsql
        ");

        // Create function to find nearest stations with route info
        DB::statement("
            CREATE OR REPLACE FUNCTION find_nearest_stations(
                user_lat DECIMAL,
                user_lng DECIMAL,
                result_limit INTEGER DEFAULT 3
            )
            RETURNS TABLE (
                station_id BIGINT,
                station_name VARCHAR,
                station_code VARCHAR,
                station_address VARCHAR,
                station_phone VARCHAR,
                distance_meters NUMERIC,
                estimated_duration_seconds NUMERIC,
                station_lat DECIMAL,
                station_lng DECIMAL
            ) AS $$
            BEGIN
                RETURN QUERY
                SELECT
                    ps.id,
                    ps.name::VARCHAR,
                    ps.code::VARCHAR,
                    ps.address::VARCHAR,
                    ps.phone::VARCHAR,
                    ROUND(
                        ST_Distance(
                            ps.geometry::geography,
                            ST_SetSRID(ST_MakePoint(user_lng, user_lat), 4326)::geography
                        )::numeric, 2
                    ) AS distance_meters,
                    ROUND(
                        (ST_Distance(
                            ps.geometry::geography,
                            ST_SetSRID(ST_MakePoint(user_lng, user_lat), 4326)::geography
                        ) / 30000.0 * 3600)::numeric, 2
                    ) AS estimated_duration_seconds,
                    ST_Y(ps.geometry)::DECIMAL,
                    ST_X(ps.geometry)::DECIMAL
                FROM police_stations ps
                WHERE ps.is_active = true
                  AND ps.geometry IS NOT NULL
                ORDER BY ps.geometry <-> ST_SetSRID(ST_MakePoint(user_lng, user_lat), 4326)
                LIMIT result_limit;
            END;
            $$ LANGUAGE plpgsql
        ");
    }

    public function down(): void
    {
        DB::statement("DROP FUNCTION IF EXISTS find_nearest_stations(DECIMAL, DECIMAL, INTEGER)");
        DB::statement("DROP FUNCTION IF EXISTS calculate_route_distance(DECIMAL, DECIMAL, DECIMAL, DECIMAL)");
    }
};
