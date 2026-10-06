-- =====================================================
-- POSTGIS SPATIAL QUERIES - SMP WebGIS Platform
-- Matola Police Incident Monitoring System
-- =====================================================

-- =====================================================
-- 1. ENABLE POSTGIS EXTENSION (Run once)
-- =====================================================
CREATE EXTENSION IF NOT EXISTS postgis;
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- =====================================================
-- 2. POPULATE geometry FROM lat/lng (After migration)
-- =====================================================

-- Populate police_stations geometry from latitude/longitude
UPDATE police_stations
SET geometry = ST_SetSRID(ST_MakePoint(longitude, latitude), 4326)
WHERE latitude IS NOT NULL AND longitude IS NOT NULL;

-- Populate incidents geometry from latitude/longitude
UPDATE incidents
SET geometry = ST_SetSRID(ST_MakePoint(longitude, latitude), 4326)
WHERE latitude IS NOT NULL AND longitude IS NOT NULL;

-- Populate districts geometry from boundary GeoJSON (if stored as text)
-- UPDATE districts
-- SET geometry = ST_GeomFromGeoJSON(boundary)
-- WHERE boundary IS NOT NULL;

-- =====================================================
-- 3. NEAREST POLICE STATION QUERY
-- =====================================================

-- Find nearest police station to a user location (lat/lng)
-- Example: User at latitude = -25.9692, longitude = 32.5732 (Matola)
SELECT
    ps.id,
    ps.name,
    ps.code,
    ps.address,
    ps.phone,
    ST_Distance(
        ps.geometry::geography,
        ST_SetSRID(ST_MakePoint(32.5732, -25.9692), 4326)::geography
    ) AS distance_meters
FROM police_stations ps
WHERE ps.is_active = true
ORDER BY ps.geometry <-> ST_SetSRID(ST_MakePoint(32.5732, -25.9692), 4326)
LIMIT 1;

-- Find top 3 nearest police stations
SELECT
    ps.id,
    ps.name,
    ps.code,
    ps.address,
    ps.phone,
    ROUND(
        ST_Distance(
            ps.geometry::geography,
            ST_SetSRID(ST_MakePoint(32.5732, -25.9692), 4326)::geography
        )::numeric, 2
    ) AS distance_meters
FROM police_stations ps
WHERE ps.is_active = true
ORDER BY ps.geometry <-> ST_SetSRID(ST_MakePoint(32.5732, -25.9692), 4326)
LIMIT 3;

-- =====================================================
-- 4. BUFFER / COVERAGE ANALYSIS
-- =====================================================

-- Create 1km buffer around each police station
SELECT
    ps.id,
    ps.name,
    ST_Buffer(ps.geometry::geography, 1000)::geometry AS buffer_1km
FROM police_stations ps
WHERE ps.is_active = true;

-- Check if an incident falls within 1km of any police station
SELECT
    i.id,
    i.title,
    ps.name AS nearest_station,
    ROUND(
        ST_Distance(
            i.geometry::geography,
            ps.geometry::geography
        )::numeric, 2
    ) AS distance_to_station_meters,
    CASE
        WHEN ST_Distance(i.geometry::geography, ps.geometry::geography) <= 1000 THEN 'Within 1km'
        WHEN ST_Distance(i.geometry::geography, ps.geometry::geography) <= 3000 THEN 'Within 3km'
        WHEN ST_Distance(i.geometry::geography, ps.geometry::geography) <= 5000 THEN 'Within 5km'
        ELSE 'Beyond 5km'
    END AS coverage_zone
FROM incidents i
CROSS JOIN LATERAL (
    SELECT ps.name, ps.geometry
    FROM police_stations ps
    WHERE ps.is_active = true
    ORDER BY ps.geometry <-> i.geometry
    LIMIT 1
) ps
WHERE i.geometry IS NOT NULL;

-- Incidents NOT covered by any police station (beyond 5km)
SELECT
    i.id,
    i.title,
    i.latitude,
    i.longitude,
    MIN(
        ST_Distance(
            i.geometry::geography,
            ps.geometry::geography
        )
    ) AS min_distance_to_station
FROM incidents i
CROSS JOIN police_stations ps
WHERE i.geometry IS NOT NULL
  AND ps.is_active = true
GROUP BY i.id, i.title, i.latitude, i.longitude
HAVING MIN(
    ST_Distance(
        i.geometry::geography,
        ps.geometry::geography
    )
) > 5000;

-- =====================================================
-- 5. INCIDENTS WITHIN SPECIFIC POLICE STATION COVERAGE
-- =====================================================

-- All incidents within 3km of a specific police station
SELECT
    i.id,
    i.reference_code,
    i.title,
    i.status,
    i.priority,
    ROUND(
        ST_Distance(
            i.geometry::geography,
            ps.geometry::geography
        )::numeric, 2
    ) AS distance_meters
FROM incidents i
CROSS JOIN police_stations ps
WHERE ps.id = 1  -- Replace with specific police_station_id
  AND i.geometry IS NOT NULL
  AND ps.geometry IS NOT NULL
  AND ST_DWithin(
      i.geometry::geography,
      ps.geometry::geography,
      3000  -- 3km radius
  )
ORDER BY distance_meters;

-- =====================================================
-- 6. BUFFER ZONE VISUALIZATION (GeoJSON for Leaflet)
-- =====================================================

-- Generate 1km buffer zones as GeoJSON for map display
SELECT
    ps.id,
    ps.name,
    ST_AsGeoJSON(
        ST_Buffer(ps.geometry::geography, 1000)::geometry
    ) AS buffer_geojson_1km,
    ST_AsGeoJSON(
        ST_Buffer(ps.geometry::geography, 3000)::geometry
    ) AS buffer_geojson_3km,
    ST_AsGeoJSON(
        ST_Buffer(ps.geometry::geography, 5000)::geometry
    ) AS buffer_geojson_5km
FROM police_stations ps
WHERE ps.is_active = true
  AND ps.geometry IS NOT NULL;

-- =====================================================
-- 7. SPATIAL QUERY WITHIN BOUNDARY (District)
-- =====================================================

-- Count incidents per district using spatial intersection
SELECT
    d.id,
    d.name,
    d.province,
    COUNT(i.id) AS incident_count
FROM districts d
LEFT JOIN incidents i ON ST_Within(i.geometry, d.geometry)
WHERE d.geometry IS NOT NULL
GROUP BY d.id, d.name, d.province
ORDER BY incident_count DESC;

-- =====================================================
-- 8. ROUTING PREPARATION (OSRM Integration)
-- =====================================================

-- Get police station coordinates for OSRM routing API
SELECT
    ps.id,
    ps.name,
    ST_Y(ps.geometry) AS latitude,
    ST_X(ps.geometry) AS longitude
FROM police_stations ps
WHERE ps.is_active = true;

-- Get incident location for OSRM routing
SELECT
    i.id,
    i.reference_code,
    ST_Y(i.geometry) AS latitude,
    ST_X(i.geometry) AS longitude
FROM incidents i
WHERE i.geometry IS NOT NULL;

-- =====================================================
-- 9. CLUSTERING / HOTSPOT ANALYSIS
-- =====================================================

-- Find crime hotspots using grid aggregation
-- Divide Matola into grid cells and count incidents
SELECT
    ROUND(ST_X(geom)::numeric, 3) AS grid_lon,
    ROUND(ST_Y(geom)::numeric, 3) AS grid_lat,
    COUNT(*) AS incident_count
FROM (
    SELECT ST_SnapToGrid(i.geometry, 0.005) AS geom
    FROM incidents i
    WHERE i.geometry IS NOT NULL
) sub
GROUP BY grid_lon, grid_lat
HAVING COUNT(*) >= 3
ORDER BY incident_count DESC;

-- =====================================================
-- 10. PROXIMITY REPORT (Distance to all stations)
-- =====================================================

-- For each incident, show distance to all active police stations
SELECT
    i.id,
    i.reference_code,
    i.title,
    ps.name AS station_name,
    ps.code AS station_code,
    ROUND(
        ST_Distance(
            i.geometry::geography,
            ps.geometry::geography
        )::numeric, 2
    ) AS distance_meters
FROM incidents i
CROSS JOIN police_stations ps
WHERE i.geometry IS NOT NULL
  AND ps.geometry IS NOT NULL
  AND ps.is_active = true
ORDER BY i.id, distance_meters;

-- =====================================================
-- 11. UPDATE TRIGGER: Auto-populate geometry from lat/lng
-- =====================================================

-- Trigger function for police_stations
CREATE OR REPLACE FUNCTION update_police_station_geometry()
RETURNS TRIGGER AS $$
BEGIN
    IF NEW.latitude IS NOT NULL AND NEW.longitude IS NOT NULL THEN
        NEW.geometry := ST_SetSRID(ST_MakePoint(NEW.longitude, NEW.latitude), 4326);
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trigger_update_police_station_geometry
    BEFORE INSERT OR UPDATE OF latitude, longitude
    ON police_stations
    FOR EACH ROW
    EXECUTE FUNCTION update_police_station_geometry();

-- Trigger function for incidents
CREATE OR REPLACE FUNCTION update_incident_geometry()
RETURNS TRIGGER AS $$
BEGIN
    IF NEW.latitude IS NOT NULL AND NEW.longitude IS NOT NULL THEN
        NEW.geometry := ST_SetSRID(ST_MakePoint(NEW.longitude, NEW.latitude), 4326);
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trigger_update_incident_geometry
    BEFORE INSERT OR UPDATE OF latitude, longitude
    ON incidents
    FOR EACH ROW
    EXECUTE FUNCTION update_incident_geometry();

-- =====================================================
-- 12. AUTO-ASSIGN NEAREST POLICE STATION
-- =====================================================

-- Function to auto-assign incident to nearest police station
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
$$ LANGUAGE plpgsql;

CREATE TRIGGER trigger_assign_nearest_station
    BEFORE INSERT ON incidents
    FOR EACH ROW
    EXECUTE FUNCTION assign_nearest_station();
