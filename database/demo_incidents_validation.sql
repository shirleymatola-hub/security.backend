SELECT
    COUNT(*) AS total_incidents,
    COUNT(*) FILTER (WHERE reference_code LIKE 'DEMO-SMP-%') AS demo_incidents,
    COUNT(*) FILTER (WHERE reference_code NOT LIKE 'DEMO-SMP-%') AS other_incidents,
    COUNT(*) FILTER (WHERE reference_code LIKE 'DEMO-SMP-%' AND deleted_at IS NOT NULL) AS demo_soft_deleted,
    COUNT(*) FILTER (WHERE reference_code LIKE 'DEMO-SMP-%' AND is_public = true) AS demo_public
FROM incidents;

WITH expected (bairro, expected_count) AS (
    VALUES
        ('Matola A', 12),
        ('Fomento', 9),
        ('Liberdade', 11),
        ('Malhampsene', 8),
        ('Matola B', 12),
        ('Matola C', 10),
        ('Matola D', 11),
        ('Matola F', 7),
        ('Matola G', 8),
        ('Matola H', 9),
        ('Matola J', 12),
        ('Mussumbuluco', 11),
        ('Cikwama', 10)
)
SELECT
    e.bairro,
    e.expected_count,
    COUNT(i.id) AS actual_count,
    COUNT(i.id) FILTER (WHERE i.is_public) AS public_count,
    CASE
        WHEN COUNT(i.id) = e.expected_count
         AND COUNT(i.id) FILTER (WHERE i.is_public) >= 2
        THEN 'OK'
        ELSE 'FAIL'
    END AS status
FROM expected e
LEFT JOIN incidents i
    ON i.neighborhood = e.bairro
   AND i.reference_code LIKE 'DEMO-SMP-%'
GROUP BY e.bairro, e.expected_count
ORDER BY e.bairro;

SELECT
    COUNT(*) AS demo_count,
    CASE WHEN COUNT(*) = 130 THEN 'OK' ELSE 'FAIL' END AS status
FROM incidents
WHERE reference_code LIKE 'DEMO-SMP-%';

SELECT reference_code, COUNT(*) AS occurrences
FROM incidents
WHERE reference_code LIKE 'DEMO-SMP-%'
GROUP BY reference_code
HAVING COUNT(*) > 1;

SELECT reference_code
FROM incidents
WHERE reference_code LIKE 'DEMO-SMP-%'
  AND reference_code !~ '^DEMO-SMP-[0-9]{4}$'
ORDER BY reference_code;

SELECT
    COUNT(*) AS demo_soft_deleted,
    CASE WHEN COUNT(*) = 0 THEN 'OK' ELSE 'FAIL' END AS status
FROM incidents
WHERE reference_code LIKE 'DEMO-SMP-%'
  AND deleted_at IS NOT NULL;

SELECT
    COUNT(*) AS invalid_geometry,
    CASE WHEN COUNT(*) = 0 THEN 'OK' ELSE 'FAIL' END AS status
FROM incidents
WHERE reference_code LIKE 'DEMO-SMP-%'
  AND (
      geometry IS NULL
      OR ST_SRID(geometry) <> 4326
      OR GeometryType(geometry) <> 'POINT'
      OR NOT ST_IsValid(geometry)
  );

SELECT
    COUNT(*) AS invalid_coordinates,
    CASE WHEN COUNT(*) = 0 THEN 'OK' ELSE 'FAIL' END AS status
FROM incidents
WHERE reference_code LIKE 'DEMO-SMP-%'
  AND (latitude IS NULL OR longitude IS NULL);

SELECT
    COUNT(*) AS invalid_incident_date,
    CASE WHEN COUNT(*) = 0 THEN 'OK' ELSE 'FAIL' END AS status
FROM incidents
WHERE reference_code LIKE 'DEMO-SMP-%'
  AND (incident_date IS NULL OR incident_date > NOW());

SELECT
    i.id,
    i.reference_code,
    i.neighborhood,
    i.latitude,
    i.longitude
FROM incidents i
WHERE i.reference_code LIKE 'DEMO-SMP-%'
  AND NOT EXISTS (
      SELECT 1
      FROM "Area de Estudo" ae
      WHERE TRIM(ae."Bairro") = i.neighborhood
        AND ae.geom IS NOT NULL
        AND ST_IsValid(ae.geom)
        AND ST_Covers(ae.geom, i.geometry)
  )
ORDER BY i.id;

SELECT
    COUNT(*) AS outside_bounds,
    CASE WHEN COUNT(*) = 0 THEN 'OK' ELSE 'FAIL' END AS status
FROM incidents
WHERE reference_code LIKE 'DEMO-SMP-%'
  AND (
      latitude IS NULL
      OR longitude IS NULL
      OR latitude NOT BETWEEN -26.10 AND -25.70
      OR longitude NOT BETWEEN 32.20 AND 32.80
  );

SELECT
    COUNT(*) AS invalid_resolved_at,
    CASE WHEN COUNT(*) = 0 THEN 'OK' ELSE 'FAIL' END AS status
FROM incidents
WHERE reference_code LIKE 'DEMO-SMP-%'
  AND (
      (
          status IN ('resolved', 'archived')
          AND (resolved_at IS NULL OR resolved_at < incident_date OR resolved_at > NOW())
      )
      OR
      (
          status IN ('pending', 'investigating')
          AND resolved_at IS NOT NULL
      )
  );

WITH expected AS (
    SELECT
        TRIM(ae."Bairro") AS bairro,
        CASE
            WHEN COUNT(DISTINCT j.police_station_id) = 1
            THEN MIN(j.police_station_id)
            ELSE NULL
        END AS expected_station_id,
        COUNT(DISTINCT j.police_station_id) AS jurisdiction_station_count
    FROM "Area de Estudo" ae
    LEFT JOIN jurisdictions j
        ON j.area_estudo_id = ae.id
       AND j.is_active = true
    WHERE TRIM(ae."Bairro") IN (
        'Matola A',
        'Fomento',
        'Liberdade',
        'Malhampsene',
        'Matola B',
        'Matola C',
        'Matola D',
        'Matola F',
        'Matola G',
        'Matola H',
        'Matola J',
        'Mussumbuluco',
        'Cikwama'
    )
      AND ae.geom IS NOT NULL
      AND ST_IsValid(ae.geom)
    GROUP BY TRIM(ae."Bairro")
),
actual AS (
    SELECT
        i.neighborhood AS bairro,
        COUNT(*) AS demo_count,
        COUNT(*) FILTER (WHERE i.police_station_id IS NOT NULL) AS non_null_station_count,
        COUNT(DISTINCT i.police_station_id) AS distinct_station_count,
        MIN(i.police_station_id) AS min_station_id,
        MAX(i.police_station_id) AS max_station_id
    FROM incidents i
    WHERE i.reference_code LIKE 'DEMO-SMP-%'
    GROUP BY i.neighborhood
)
SELECT
    e.bairro,
    e.jurisdiction_station_count,
    e.expected_station_id,
    a.demo_count,
    a.non_null_station_count,
    a.distinct_station_count,
    a.min_station_id,
    a.max_station_id,
    CASE
        WHEN a.demo_count IS NULL THEN 'FAIL'
        WHEN e.expected_station_id IS NULL
             AND COALESCE(a.non_null_station_count, 0) = 0 THEN 'OK'
        WHEN COALESCE(a.distinct_station_count, 0) = 1
             AND a.non_null_station_count = a.demo_count
             AND a.min_station_id = e.expected_station_id
             AND a.max_station_id = e.expected_station_id THEN 'OK'
        ELSE 'FAIL'
    END AS status
FROM expected e
LEFT JOIN actual a ON a.bairro = e.bairro
ORDER BY e.bairro;

SELECT c.name AS category, COUNT(*) AS demo_count
FROM incidents i
INNER JOIN categories c ON c.id = i.category_id
WHERE i.reference_code LIKE 'DEMO-SMP-%'
GROUP BY c.name
ORDER BY demo_count DESC, c.name;

SELECT status, priority, COUNT(*) AS demo_count
FROM incidents
WHERE reference_code LIKE 'DEMO-SMP-%'
GROUP BY status, priority
ORDER BY status, priority;

SELECT neighborhood, COUNT(*) AS public_demo_count
FROM incidents
WHERE reference_code LIKE 'DEMO-SMP-%'
  AND is_public = true
GROUP BY neighborhood
ORDER BY neighborhood;

SELECT
    COUNT(*) AS non_public_demo,
    CASE WHEN COUNT(*) = 0 THEN 'OK' ELSE 'FAIL' END AS status
FROM incidents
WHERE reference_code LIKE 'DEMO-SMP-%'
  AND (is_public = false OR is_public IS NULL);

SELECT
    i.id AS incident_id,
    i.reference_code,
    i2.id AS duplicate_id,
    i2.reference_code AS duplicate_reference_code,
    i.latitude,
    i.longitude
FROM incidents i
INNER JOIN incidents i2
    ON i.id < i2.id
   AND ST_Equals(i.geometry, i2.geometry)
WHERE i.reference_code LIKE 'DEMO-SMP-%'
   OR i2.reference_code LIKE 'DEMO-SMP-%'
ORDER BY i.id, i2.id;

WITH checks AS (
    SELECT
        'demo_total_130' AS check_name,
        CASE WHEN COUNT(*) = 130 THEN 0 ELSE 1 END AS failures
    FROM incidents
    WHERE reference_code LIKE 'DEMO-SMP-%'

    UNION ALL

    SELECT
        'demo_soft_deleted',
        COUNT(*)
    FROM incidents
    WHERE reference_code LIKE 'DEMO-SMP-%'
      AND deleted_at IS NOT NULL

    UNION ALL

    SELECT
        'invalid_geometry',
        COUNT(*)
    FROM incidents
    WHERE reference_code LIKE 'DEMO-SMP-%'
      AND (
          geometry IS NULL
          OR ST_SRID(geometry) <> 4326
          OR GeometryType(geometry) <> 'POINT'
          OR NOT ST_IsValid(geometry)
      )

    UNION ALL

    SELECT
        'invalid_coordinates',
        COUNT(*)
    FROM incidents
    WHERE reference_code LIKE 'DEMO-SMP-%'
      AND (latitude IS NULL OR longitude IS NULL)

    UNION ALL

    SELECT
        'invalid_incident_date',
        COUNT(*)
    FROM incidents
    WHERE reference_code LIKE 'DEMO-SMP-%'
      AND (incident_date IS NULL OR incident_date > NOW())

    UNION ALL

    SELECT
        'outside_bairro',
        COUNT(*)
    FROM incidents i
    WHERE i.reference_code LIKE 'DEMO-SMP-%'
      AND NOT EXISTS (
          SELECT 1
          FROM "Area de Estudo" ae
          WHERE TRIM(ae."Bairro") = i.neighborhood
            AND ae.geom IS NOT NULL
            AND ST_IsValid(ae.geom)
            AND ST_Covers(ae.geom, i.geometry)
      )

    UNION ALL

    SELECT
        'outside_bounds',
        COUNT(*)
    FROM incidents
    WHERE reference_code LIKE 'DEMO-SMP-%'
      AND (
          latitude IS NULL
          OR longitude IS NULL
          OR latitude NOT BETWEEN -26.10 AND -25.70
          OR longitude NOT BETWEEN 32.20 AND 32.80
      )

    UNION ALL

    SELECT
        'invalid_resolved_at',
        COUNT(*)
    FROM incidents
    WHERE reference_code LIKE 'DEMO-SMP-%'
      AND (
          (
              status IN ('resolved', 'archived')
              AND (resolved_at IS NULL OR resolved_at < incident_date OR resolved_at > NOW())
          )
          OR
          (
              status IN ('pending', 'investigating')
              AND resolved_at IS NOT NULL
          )
      )

    UNION ALL

    SELECT
        'non_public_demo',
        COUNT(*)
    FROM incidents
    WHERE reference_code LIKE 'DEMO-SMP-%'
      AND (is_public = false OR is_public IS NULL)

    UNION ALL

    SELECT
        'bairro_count_mismatch',
        COUNT(*)
    FROM (
        VALUES
            ('Matola A', 12),
            ('Fomento', 9),
            ('Liberdade', 11),
            ('Malhampsene', 8),
            ('Matola B', 12),
            ('Matola C', 10),
            ('Matola D', 11),
            ('Matola F', 7),
            ('Matola G', 8),
            ('Matola H', 9),
            ('Matola J', 12),
            ('Mussumbuluco', 11),
            ('Cikwama', 10)
    ) expected_bairros (bairro, expected_count)
    WHERE (
        SELECT COUNT(*)
        FROM incidents i
        WHERE i.reference_code LIKE 'DEMO-SMP-%'
          AND i.neighborhood = expected_bairros.bairro
    ) <> expected_bairros.expected_count

    UNION ALL

    SELECT
        'bairros_sem_2_publicas',
        COUNT(*)
    FROM (
        VALUES
            ('Matola A'),
            ('Fomento'),
            ('Liberdade'),
            ('Malhampsene'),
            ('Matola B'),
            ('Matola C'),
            ('Matola D'),
            ('Matola F'),
            ('Matola G'),
            ('Matola H'),
            ('Matola J'),
            ('Mussumbuluco'),
            ('Cikwama')
    ) expected_bairros (bairro)
    WHERE (
        SELECT COUNT(*)
        FROM incidents i
        WHERE i.reference_code LIKE 'DEMO-SMP-%'
          AND i.is_public = true
          AND i.neighborhood = expected_bairros.bairro
    ) < 2

    UNION ALL

    SELECT
        'duplicate_geometry',
        COUNT(*)
    FROM (
        SELECT i.id
        FROM incidents i
        INNER JOIN incidents i2
            ON i.id < i2.id
           AND ST_Equals(i.geometry, i2.geometry)
        WHERE i.reference_code LIKE 'DEMO-SMP-%'
           OR i2.reference_code LIKE 'DEMO-SMP-%'
    ) duplicates
)
SELECT
    check_name,
    failures,
    CASE WHEN failures = 0 THEN 'OK' ELSE 'FAIL' END AS status
FROM checks
ORDER BY check_name;
