-- =====================================================================
-- 04. Coordenadas de las estaciones para el seguimiento en vivo (mapa)
-- Agrega latitud y longitud a la tabla estaciones y las llena para las
-- 28 estaciones. Se puede ejecutar varias veces.
--
-- IMPORTANTE: las coordenadas son APROXIMADAS (trazadas a mano sobre
-- OpenStreetMap) y sirven solo para la demostración del mapa:
--   L12: Centra Sur -> Plaza El Amate por la Calzada Aguilar Batres y la
--        Avenida Bolívar.
--   L1 : Centro Histórico, zona 1.
--   L2 : San Sebastián -> Hipódromo del Norte y Jocotenango, zona 2.
-- =====================================================================
USE transmetro;

-- 1. Columnas (solo si no existen; MySQL 8 no tiene ADD COLUMN IF NOT EXISTS)
SET @existe := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'estaciones' AND COLUMN_NAME = 'latitud'
);
SET @sql := IF(@existe = 0,
  'ALTER TABLE estaciones
     ADD COLUMN latitud  DECIMAL(9,6) NULL AFTER direccion,
     ADD COLUMN longitud DECIMAL(9,6) NULL AFTER latitud,
     ADD CONSTRAINT chk_estaciones_latitud  CHECK (latitud  IS NULL OR latitud  BETWEEN -90  AND 90),
     ADD CONSTRAINT chk_estaciones_longitud CHECK (longitud IS NULL OR longitud BETWEEN -180 AND 180)',
  'SELECT ''Las columnas latitud y longitud ya existen'' AS aviso');
PREPARE sentencia FROM @sql;
EXECUTE sentencia;
DEALLOCATE PREPARE sentencia;

-- 2. Coordenadas aproximadas (por nombre, que es único)
-- Línea 12 - Eje Sur
UPDATE estaciones SET latitud = 14.558000, longitud = -90.578500 WHERE nombre = 'Centra Sur';
UPDATE estaciones SET latitud = 14.568000, longitud = -90.565500 WHERE nombre = 'Monte María';
UPDATE estaciones SET latitud = 14.576000, longitud = -90.559000 WHERE nombre = 'Javier';
UPDATE estaciones SET latitud = 14.584500, longitud = -90.553000 WHERE nombre = 'Las Charcas';
UPDATE estaciones SET latitud = 14.592500, longitud = -90.546500 WHERE nombre = 'El Carmen';
UPDATE estaciones SET latitud = 14.601000, longitud = -90.541000 WHERE nombre = 'Reformita';
UPDATE estaciones SET latitud = 14.608500, longitud = -90.536000 WHERE nombre = 'Mariscal';
UPDATE estaciones SET latitud = 14.620000, longitud = -90.530000 WHERE nombre = 'Trébol';
UPDATE estaciones SET latitud = 14.625000, longitud = -90.526000 WHERE nombre = 'Santa Cecilia';
UPDATE estaciones SET latitud = 14.629500, longitud = -90.522500 WHERE nombre = 'Bolívar';
UPDATE estaciones SET latitud = 14.633500, longitud = -90.519000 WHERE nombre = 'Don Bosco';
UPDATE estaciones SET latitud = 14.636500, longitud = -90.515000 WHERE nombre = 'Plaza Municipal';
UPDATE estaciones SET latitud = 14.634500, longitud = -90.512500 WHERE nombre = 'Plaza Barrios';
UPDATE estaciones SET latitud = 14.635000, longitud = -90.509000 WHERE nombre = 'Plaza El Amate';

-- Línea 1 - Centro Histórico
UPDATE estaciones SET latitud = 14.650000, longitud = -90.517000 WHERE nombre = 'Gómez Carrillo';
UPDATE estaciones SET latitud = 14.646500, longitud = -90.516500 WHERE nombre = 'San Agustín';
UPDATE estaciones SET latitud = 14.643000, longitud = -90.514000 WHERE nombre = 'Parque Centenario';
UPDATE estaciones SET latitud = 14.646000, longitud = -90.510500 WHERE nombre = 'San Sebastián';
UPDATE estaciones SET latitud = 14.641300, longitud = -90.512000 WHERE nombre = 'Mercado Central';
UPDATE estaciones SET latitud = 14.640000, longitud = -90.509500 WHERE nombre = 'Correos';
UPDATE estaciones SET latitud = 14.638500, longitud = -90.506000 WHERE nombre = 'Beatas de Belén';
UPDATE estaciones SET latitud = 14.636500, longitud = -90.507500 WHERE nombre = 'Paseo de las Letras';
UPDATE estaciones SET latitud = 14.632000, longitud = -90.512000 WHERE nombre = 'Centro Cívico';
UPDATE estaciones SET latitud = 14.629000, longitud = -90.509500 WHERE nombre = 'Sur 2';

-- Línea 2 - Hipódromo
UPDATE estaciones SET latitud = 14.652000, longitud = -90.510000 WHERE nombre = 'Simeón Cañas';
UPDATE estaciones SET latitud = 14.658000, longitud = -90.508500 WHERE nombre = 'Hipódromo del Norte';
UPDATE estaciones SET latitud = 14.663000, longitud = -90.507000 WHERE nombre = 'San José de la Montaña';
UPDATE estaciones SET latitud = 14.667000, longitud = -90.511000 WHERE nombre = 'Jocotenango';

-- 3. Verificación: las 28 estaciones deben tener coordenadas.
SELECT COUNT(*) AS estaciones, SUM(latitud IS NULL OR longitud IS NULL) AS sin_coordenadas FROM estaciones;
