<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ============================================================
        // 1. ONE-TIME DATA FIX: sync lat/lng from geometry centroids
        //    (neighborhoods & districts have geometry as source of truth
        //     because it was imported from external spatial data)
        // ============================================================
        DB::statement("
            UPDATE neighborhoods
            SET latitude  = ST_Y(ST_Centroid(geometry)),
                longitude = ST_X(ST_Centroid(geometry))
            WHERE geometry IS NOT NULL
        ");

        DB::statement("
            UPDATE districts
            SET latitude  = ST_Y(ST_Centroid(geometry)),
                longitude = ST_X(ST_Centroid(geometry))
            WHERE geometry IS NOT NULL
        ");

        // ============================================================
        // 2. TRIGGERS: keep lat/lng in sync when geometry changes
        //    (for neighborhoods — polygon source of truth)
        // ============================================================
        DB::statement("
            CREATE OR REPLACE FUNCTION update_neighborhood_latlng_from_geometry()
            RETURNS TRIGGER AS $$
            BEGIN
                IF NEW.geometry IS NOT NULL THEN
                    NEW.latitude  := ST_Y(ST_Centroid(NEW.geometry));
                    NEW.longitude := ST_X(ST_Centroid(NEW.geometry));
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql
        ");

        DB::statement("
            CREATE TRIGGER trigger_update_neighborhood_latlng
                BEFORE INSERT OR UPDATE OF geometry
                ON neighborhoods
                FOR EACH ROW
                EXECUTE FUNCTION update_neighborhood_latlng_from_geometry()
        ");

        // ============================================================
        // 3. TRIGGERS: keep lat/lng in sync when geometry changes
        //    (for districts — polygon source of truth)
        // ============================================================
        DB::statement("
            CREATE OR REPLACE FUNCTION update_district_latlng_from_geometry()
            RETURNS TRIGGER AS $$
            BEGIN
                IF NEW.geometry IS NOT NULL THEN
                    NEW.latitude  := ST_Y(ST_Centroid(NEW.geometry));
                    NEW.longitude := ST_X(ST_Centroid(NEW.geometry));
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql
        ");

        DB::statement("
            CREATE TRIGGER trigger_update_district_latlng
                BEFORE INSERT OR UPDATE OF geometry
                ON districts
                FOR EACH ROW
                EXECUTE FUNCTION update_district_latlng_from_geometry()
        ");
    }

    public function down(): void
    {
        DB::statement("DROP TRIGGER IF EXISTS trigger_update_district_latlng ON districts");
        DB::statement("DROP FUNCTION IF EXISTS update_district_latlng_from_geometry()");
        DB::statement("DROP TRIGGER IF EXISTS trigger_update_neighborhood_latlng ON neighborhoods");
        DB::statement("DROP FUNCTION IF EXISTS update_neighborhood_latlng_from_geometry()");
    }
};
