<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Create trigger to auto-populate geometry for police_stations
        DB::statement("
            CREATE OR REPLACE FUNCTION update_police_station_geometry()
            RETURNS TRIGGER AS $$
            BEGIN
                IF NEW.latitude IS NOT NULL AND NEW.longitude IS NOT NULL THEN
                    NEW.geometry := ST_SetSRID(ST_MakePoint(NEW.longitude, NEW.latitude), 4326);
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql
        ");

        DB::statement("
            CREATE TRIGGER trigger_update_police_station_geometry
                BEFORE INSERT OR UPDATE OF latitude, longitude
                ON police_stations
                FOR EACH ROW
                EXECUTE FUNCTION update_police_station_geometry()
        ");

        // Create trigger to auto-populate geometry for incidents
        DB::statement("
            CREATE OR REPLACE FUNCTION update_incident_geometry()
            RETURNS TRIGGER AS $$
            BEGIN
                IF NEW.latitude IS NOT NULL AND NEW.longitude IS NOT NULL THEN
                    NEW.geometry := ST_SetSRID(ST_MakePoint(NEW.longitude, NEW.latitude), 4326);
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql
        ");

        DB::statement("
            CREATE TRIGGER trigger_update_incident_geometry
                BEFORE INSERT OR UPDATE OF latitude, longitude
                ON incidents
                FOR EACH ROW
                EXECUTE FUNCTION update_incident_geometry()
        ");

        // Create trigger to auto-assign nearest police station
        DB::statement("
            CREATE OR REPLACE FUNCTION assign_nearest_station()
            RETURNS TRIGGER AS $$
            BEGIN
                IF NEW.geometry IS NOT NULL AND NEW.police_station_id IS NULL THEN
                    NEW.police_station_id := (
                        SELECT ps.id
                        FROM police_stations ps
                        WHERE ps.is_active = true
                          AND ps.geometry IS NOT NULL
                        ORDER BY ps.geometry <-> NEW.geometry
                        LIMIT 1
                    );
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql
        ");

        DB::statement("
            CREATE TRIGGER trigger_assign_nearest_station
                BEFORE INSERT ON incidents
                FOR EACH ROW
                EXECUTE FUNCTION assign_nearest_station()
        ");
    }

    public function down(): void
    {
        DB::statement("DROP TRIGGER IF EXISTS trigger_assign_nearest_station ON incidents");
        DB::statement("DROP FUNCTION IF EXISTS assign_nearest_station()");
        DB::statement("DROP TRIGGER IF EXISTS trigger_update_incident_geometry ON incidents");
        DB::statement("DROP FUNCTION IF EXISTS update_incident_geometry()");
        DB::statement("DROP TRIGGER IF EXISTS trigger_update_police_station_geometry ON police_stations");
        DB::statement("DROP FUNCTION IF EXISTS update_police_station_geometry()");
    }
};
