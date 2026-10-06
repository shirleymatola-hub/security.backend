# PostgreSQL + PostGIS Migration Guide
## SMP WebGIS Platform - Matola Police Incident Monitoring

---

## Overview

This document describes the complete migration from MySQL to PostgreSQL with PostGIS spatial capabilities for the SMP WebGIS Platform.

---

## Prerequisites

### 1. Install PostgreSQL
- Download and install PostgreSQL 15+ from https://www.postgresql.org/download/
- Default port: 5432
- Default user: postgres

### 2. Install PostGIS Extension
```sql
-- Connect to PostgreSQL as superuser
psql -U postgres

-- Create the database
CREATE DATABASE smp;

-- Connect to the database
\c smp

-- Enable PostGIS extension
CREATE EXTENSION IF NOT EXISTS postgis;
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
```

### 3. PHP Extensions Required
Ensure these PHP extensions are enabled in `php.ini`:
```ini
extension=pdo_pgsql
extension=pgsql
extension=pgsql
```

---

## Migration Steps

### Step 1: Backup Current Database
```bash
mysqldump -u root -p smp > smp_mysql_backup.sql
```

### Step 2: Update Environment Configuration
The `.env` file has been updated:
```ini
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=smp
DB_USERNAME=postgres
DB_PASSWORD=
```

### Step 3: Run Laravel Migrations
```bash
php artisan migrate:fresh
```

### Step 4: Populate Spatial Data
After migration, run the spatial data population:
```bash
psql -U postgres -d smp -f database/spatial_queries.sql
```

Or run individual UPDATE statements:
```sql
-- Populate police_stations geometry
UPDATE police_stations
SET geometry = ST_SetSRID(ST_MakePoint(longitude, latitude), 4326)
WHERE latitude IS NOT NULL AND longitude IS NOT NULL;

-- Populate incidents geometry
UPDATE incidents
SET geometry = ST_SetSRID(ST_MakePoint(longitude, latitude), 4326)
WHERE latitude IS NOT NULL AND longitude IS NOT NULL;
```

---

## Database Changes Summary

### New Extension
| Extension | Purpose |
|-----------|---------|
| `postgis` | Spatial data types and functions |
| `uuid-ossp` | UUID generation |

### Modified Tables with PostGIS Geometry

#### districts
| Column | Type | Description |
|--------|------|-------------|
| `geometry` | `GEOMETRY(POLYGON, 4326)` | District boundary polygon |

#### neighborhoods
| Column | Type | Description |
|--------|------|-------------|
| `geometry` | `GEOMETRY(POLYGON, 4326)` | Neighborhood boundary polygon |

#### police_stations
| Column | Type | Description |
|--------|------|-------------|
| `geometry` | `GEOMETRY(POINT, 4326)` | Station location point |

#### incidents
| Column | Type | Description |
|--------|------|-------------|
| `geometry` | `GEOMETRY(POINT, 4326)` | Incident location point |

### Spatial Indexes Created
```sql
CREATE INDEX idx_districts_geometry ON districts USING GIST (geometry);
CREATE INDEX idx_neighborhoods_geometry ON neighborhoods USING GIST (geometry);
CREATE INDEX idx_police_stations_geometry ON police_stations USING GIST (geometry);
CREATE INDEX idx_incidents_geometry ON incidents USING GIST (geometry);
```

---

## Spatial Triggers (Auto-Populate Geometry)

### Trigger: update_police_station_geometry
Automatically creates `geometry` from `latitude`/`longitude` when inserting or updating police stations.

### Trigger: update_incident_geometry
Automatically creates `geometry` from `latitude`/`longitude` when inserting or updating incidents.

### Trigger: assign_nearest_station
Automatically assigns an incident to the nearest active police station when no station is specified.

---

## Spatial Functions Created

### get_incidents_in_coverage(station_id, radius_meters)
Returns all incidents within a specified radius of a police station.

```sql
SELECT * FROM get_incidents_in_coverage(1, 3000);
```

### calculate_route_distance(origin_lat, origin_lng, dest_lat, dest_lng)
Calculates distance and estimated duration between two points.

```sql
SELECT * FROM calculate_route_distance(-25.9692, 32.5732, -25.9800, 32.5900);
```

### find_nearest_stations(user_lat, user_lng, result_limit)
Finds nearest police stations with route information.

```sql
SELECT * FROM find_nearest_stations(-25.9692, 32.5732, 3);
```

---

## Spatial Query Examples

### 1. Find Nearest Police Station
```sql
SELECT
    ps.id,
    ps.name,
    ST_Distance(
        ps.geometry::geography,
        ST_SetSRID(ST_MakePoint(32.5732, -25.9692), 4326)::geography
    ) AS distance_meters
FROM police_stations ps
WHERE ps.is_active = true
ORDER BY ps.geometry <-> ST_SetSRID(ST_MakePoint(32.5732, -25.9692), 4326)
LIMIT 1;
```

### 2. Buffer Zone Analysis (1km, 3km, 5km)
```sql
SELECT
    ps.id,
    ps.name,
    ST_Buffer(ps.geometry::geography, 1000)::geometry AS buffer_1km,
    ST_Buffer(ps.geometry::geography, 3000)::geometry AS buffer_3km,
    ST_Buffer(ps.geometry::geography, 5000)::geometry AS buffer_5km
FROM police_stations ps
WHERE ps.is_active = true;
```

### 3. Incidents Within 3km of Station
```sql
SELECT i.id, i.title,
    ST_Distance(i.geometry::geography, ps.geometry::geography) AS distance
FROM incidents i
CROSS JOIN police_stations ps
WHERE ps.id = 1
  AND ST_DWithin(i.geometry::geography, ps.geometry::geography, 3000);
```

### 4. Coverage Zone Report
```sql
SELECT * FROM police_station_coverage;
```

---

## WebGIS Integration

### Leaflet + OpenStreetMap
```javascript
// Initialize map centered on Matola
var map = L.map('map').setView([-25.9692, 32.5732], 13);

// Add OpenStreetMap layer
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors'
}).addTo(map);

// Fetch police stations as GeoJSON
fetch('/api/police-stations/geojson')
    .then(response => response.json())
    .then(data => {
        L.geoJSON(data).addTo(map);
    });
```

### GeoServer WMS/WFS Layers
```xml
<!-- Feature Type: police_stations -->
<featureType>
  <name>police_stations</name>
  <nativeName>police_stations</nativeName>
  <srs>EPSG:4326</srs>
  <geometry>
    <name>geometry</name>
    <type>Point</type>
    <srs>EPSG:4326</srs>
  </geometry>
</featureType>

<!-- Feature Type: incidents -->
<featureType>
  <name>incidents</name>
  <nativeName>incidents</nativeName>
  <srs>EPSG:4326</srs>
  <geometry>
    <name>geometry</name>
    <type>Point</type>
    <srs>EPSG:4326</srs>
  </geometry>
</featureType>
```

### OSRM Routing Integration
```javascript
// Get route from user to nearest police station
function getRoute(userLat, userLng, stationLat, stationLng) {
    const url = `https://router.project-osrm.org/route/v1/driving/${userLng},${userLat};${stationLng},${stationLat}?overview=full&geometries=geojson`;

    return fetch(url)
        .then(response => response.json())
        .then(data => {
            const route = data.routes[0];
            return {
                geometry: route.geometry,
                distance: route.distance, // meters
                duration: route.duration  // seconds
            };
        });
}
```

---

## File Structure

```
database/
├── migrations/
│   ├── 0001_01_01_000000_create_postgis_extension.php
│   ├── 0001_01_01_000000_create_users_table.php
│   ├── 0001_01_01_000001_create_cache_table.php
│   ├── 0001_01_01_000002_create_jobs_table.php
│   ├── 2026_06_30_150842_create_permission_tables.php
│   ├── 2026_06_30_150900_create_districts_table.php
│   ├── 2026_06_30_150901_create_neighborhoods_table.php
│   ├── 2026_06_30_150902_create_categories_table.php
│   ├── 2026_06_30_150903_create_police_stations_table.php
│   ├── 2026_06_30_150904_create_incidents_table.php
│   ├── 2026_06_30_150905_create_incident_updates_table.php
│   ├── 2026_06_30_150906_create_incident_attachments_table.php
│   ├── 2026_06_30_150907_add_fields_to_users_table.php
│   ├── 2026_06_30_150908_create_spatial_triggers.php
│   ├── 2026_06_30_150909_create_coverage_analysis.php
│   └── 2026_06_30_150910_create_routing_functions.php
├── spatial_queries.sql
```

---

## Key PostgreSQL vs MySQL Differences

| Feature | MySQL | PostgreSQL |
|---------|-------|------------|
| Auto-increment | `AUTO_INCREMENT` | `BIGSERIAL` / Laravel `increments()` |
| Boolean | `TINYINT(1)` | `BOOLEAN` |
| Enum | `ENUM(...)` | `VARCHAR` + check constraint |
| Spatial Type | `POINT`, `POLYGON` | `GEOMETRY(POINT, 4326)` |
| Spatial Index | `SPATIAL INDEX` | `GIST INDEX` |
| JSON | `JSON` | `JSONB` (recommended) |
| Text | `TEXT` | `TEXT` |
| Long Text | `LONGTEXT` | `TEXT` |

---

## Troubleshooting

### Error: function st_distance does not exist
Make sure PostGIS extension is enabled:
```sql
CREATE EXTENSION IF NOT EXISTS postgis;
```

### Error: column "geometry" does not exist
Run the geometry population queries after migration:
```sql
UPDATE police_stations
SET geometry = ST_SetSRID(ST_MakePoint(longitude, latitude), 4326);
```

### Error: permission denied for table
Grant proper permissions to the database user:
```sql
GRANT ALL PRIVILEGES ON DATABASE smp TO your_user;
GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO your_user;
GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public TO your_user;
```
