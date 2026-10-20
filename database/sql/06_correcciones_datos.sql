-- =====================================================================
-- 06. Correcciones de datos: tildes en los textos del script inicial
-- MySQL 8.0 · Base transmetro · Idempotente (se puede ejecutar varias veces)
--
-- Solo cambia textos que todavía están sin tilde; si ya se corrigieron, no hace nada.
-- La bitácora NO se toca: es un registro de auditoría y sus filas no se editan.
-- 01_transmetro.sql ya trae estos textos corregidos (este script es para bases creadas antes).
--
-- Compatible con el "modo de actualización segura" de MySQL Workbench: cada UPDATE filtra por la
-- llave (id > 0). La comparación usa utf8mb4_bin porque la intercalación de la base no distingue
-- tildes: así, en una segunda ejecución no cambia ninguna fila.
-- =====================================================================
USE transmetro;

START TRANSACTION;

-- Líneas
UPDATE lineas SET nombre = 'Línea 12 - Eje Sur'
 WHERE id > 0 AND codigo = 'L12' AND nombre COLLATE utf8mb4_bin = 'Linea 12 - Eje Sur';
UPDATE lineas SET nombre = 'Línea 1 - Centro Histórico'
 WHERE id > 0 AND codigo = 'L1' AND nombre COLLATE utf8mb4_bin = 'Linea 1 - Centro Historico';
UPDATE lineas SET nombre = 'Línea 2 - Hipódromo'
 WHERE id > 0 AND codigo = 'L2' AND nombre COLLATE utf8mb4_bin = 'Linea 2 - Hipodromo';

-- Parqueos
UPDATE parqueos SET nombre = 'Predio Centro Histórico' WHERE id > 0 AND nombre COLLATE utf8mb4_bin = 'Predio Centro Historico';
UPDATE parqueos SET nombre = 'Predio Hipódromo'        WHERE id > 0 AND nombre COLLATE utf8mb4_bin = 'Predio Hipodromo';

-- Historial educativo de los pilotos
UPDATE historial_educativo SET institucion = 'Instituto Técnico Vocacional Dr. Imrich Fischmann'
 WHERE id > 0 AND institucion COLLATE utf8mb4_bin = 'Instituto Tecnico Vocacional Dr. Imrich Fischmann';
UPDATE historial_educativo SET institucion = 'Instituto Nacional de Educación Básica'
 WHERE id > 0 AND institucion COLLATE utf8mb4_bin = 'Instituto Nacional de Educacion Basica';
UPDATE historial_educativo SET titulo = 'Perito en Mecánica Automotriz' WHERE id > 0 AND titulo COLLATE utf8mb4_bin = 'Perito en Mecanica Automotriz';
UPDATE historial_educativo SET titulo = 'Tercero Básico'                WHERE id > 0 AND titulo COLLATE utf8mb4_bin = 'Tercero Basico';
UPDATE historial_educativo SET titulo = 'Técnico en Transporte'         WHERE id > 0 AND titulo COLLATE utf8mb4_bin = 'Tecnico en Transporte';

-- Alerta de ejemplo del script inicial
UPDATE alertas SET mensaje = 'Estación Trébol con 155 pasajeros en espera: enviar otro bus.'
 WHERE id > 0 AND mensaje COLLATE utf8mb4_bin = 'Estacion Trebol con 155 pasajeros en espera: enviar otro bus.';

COMMIT;

-- ---------------------------------------------------------------------
-- Verificación: todas las filas deben salir 0.
-- La intercalación de la base (utf8mb4_unicode_ci) no distingue tildes: se compara con utf8mb4_bin.
-- ---------------------------------------------------------------------
SELECT 'lineas sin tilde' AS revision, COUNT(*) AS filas FROM lineas
 WHERE nombre COLLATE utf8mb4_bin IN ('Linea 12 - Eje Sur', 'Linea 1 - Centro Historico', 'Linea 2 - Hipodromo')
UNION ALL SELECT 'parqueos sin tilde', COUNT(*) FROM parqueos
 WHERE nombre COLLATE utf8mb4_bin IN ('Predio Centro Historico', 'Predio Hipodromo')
UNION ALL SELECT 'historial sin tilde', COUNT(*) FROM historial_educativo
 WHERE institucion COLLATE utf8mb4_bin IN ('Instituto Tecnico Vocacional Dr. Imrich Fischmann', 'Instituto Nacional de Educacion Basica')
    OR titulo COLLATE utf8mb4_bin IN ('Perito en Mecanica Automotriz', 'Tercero Basico', 'Tecnico en Transporte')
UNION ALL SELECT 'alertas sin tilde', COUNT(*) FROM alertas
 WHERE mensaje COLLATE utf8mb4_bin = 'Estacion Trebol con 155 pasajeros en espera: enviar otro bus.';
