-- =====================================================================
-- 07. Datos de demostración: historial de los últimos 7 días (fechas relativas a NOW())
-- MySQL 8.0 · Base transmetro · Ejecutar después de 01
--
-- Carga recorridos en horas pico con sus llegadas, pasajeros en espera, alertas, 20 tarjetas
-- (TM-000101 a TM-000120) con recargas y pasajes, y cierra los recorridos viejos del script inicial.
--
-- El saldo lo calculan los triggers: las transacciones se insertan en orden cronológico.
-- Idempotente: deja la marca 'cargar_datos_demo' en la bitácora y, si ya existe, no hace nada.
-- Todo va en una transacción y funciona con el modo de actualización segura de Workbench.
-- =====================================================================
USE transmetro;
-- Hora de Guatemala para NOW() aunque el servidor de MySQL esté en UTC.
SET time_zone = '-06:00';

SET @ahora := NOW();
-- Marca de las filas de esta carga (updated_at); created_at lleva la fecha "real" del dato.
SET @demo := @ahora;
SET @hay := (SELECT COUNT(*) FROM bitacora WHERE accion = 'cargar_datos_demo');
SET @admin := (SELECT MIN(id) FROM users WHERE rol = 'administrador');

START TRANSACTION;

-- ---------------------------------------------------------------------
-- 1. Recorridos finalizados: 9 salidas por línea y día; los de hoy, solo si ya terminaron.
--    Duración: 4 minutos entre estaciones (+5 de espera en el de mediodía).
-- ---------------------------------------------------------------------
INSERT INTO recorridos (linea_id, bus_id, piloto_id, inicio, fin, estado, created_at, updated_at)
SELECT x.linea_id, b.id, p.id, x.inicio, x.inicio + INTERVAL x.duracion MINUTE, 'finalizado', x.inicio, @demo
FROM (
    SELECT l.id AS linea_id, l.fila, l.buses, d.d, s.idx,
           TIMESTAMP(DATE(@ahora) - INTERVAL d.d DAY, s.hora) AS inicio,
           (l.estaciones - 1) * 4 + 1 + IF(s.idx = 4, 5, 0) AS duracion
    FROM (SELECT ln.id, ROW_NUMBER() OVER (ORDER BY ln.id) AS fila,
                 (SELECT COUNT(*) FROM linea_estacion le WHERE le.linea_id = ln.id) AS estaciones,
                 (SELECT COUNT(*) FROM buses bb WHERE bb.linea_id = ln.id) AS buses
          FROM lineas ln WHERE ln.estado = 'activa') l
    CROSS JOIN (SELECT 0 AS d UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3
                UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6) d
    CROSS JOIN (SELECT 0 AS idx, '06:00:00' AS hora UNION ALL SELECT 1, '06:30:00' UNION ALL SELECT 2, '07:00:00'
                UNION ALL SELECT 3, '07:30:00' UNION ALL SELECT 4, '12:15:00' UNION ALL SELECT 5, '17:00:00'
                UNION ALL SELECT 6, '17:30:00' UNION ALL SELECT 7, '18:00:00' UNION ALL SELECT 8, '18:30:00') s
    WHERE l.estaciones > 1 AND l.buses > 0
) x
-- Bus: uno distinto por salida dentro de la línea; piloto: rotación que no repite en salidas simultáneas.
JOIN (SELECT id, linea_id, ROW_NUMBER() OVER (PARTITION BY linea_id ORDER BY id) - 1 AS n
      FROM buses WHERE linea_id IS NOT NULL) b ON b.linea_id = x.linea_id AND b.n = MOD(x.idx, x.buses)
JOIN (SELECT id, ROW_NUMBER() OVER (ORDER BY id) - 1 AS n, COUNT(*) OVER () AS total
      FROM pilotos WHERE estado = 'activo') p ON p.n = MOD(x.idx * 3 + x.fila - 1 + x.d * 5, p.total)
WHERE @hay = 0 AND x.inicio + INTERVAL x.duracion MINUTE <= @ahora
ORDER BY x.inicio, x.linea_id;

-- ---------------------------------------------------------------------
-- 2. Llegadas a cada estación. Ocupación en campana a lo largo de la línea: más alta en
--    horas pico; en la salida de mediodía el bus sale casi vacío (18 %) y espera 5 minutos (RN-16).
-- ---------------------------------------------------------------------
INSERT INTO recorrido_estaciones (recorrido_id, estacion_id, hora_llegada, hora_salida, pasajeros_a_bordo, espera_extra_min, created_at, updated_at)
SELECT r.id, le.estacion_id,
       r.inicio + INTERVAL ((le.orden - 1) * 4 + IF(HOUR(r.inicio) = 12 AND le.orden > 1, 5, 0)) MINUTE,
       r.inicio + INTERVAL ((le.orden - 1) * 4 + IF(HOUR(r.inicio) = 12 AND le.orden > 1, 5, 0) + 1 + IF(HOUR(r.inicio) = 12 AND le.orden = 1, 5, 0)) MINUTE,
       LEAST(b.capacidad_maxima, ROUND(b.capacidad_maxima * CASE
           WHEN HOUR(r.inicio) = 12 AND le.orden = 1 THEN 0.18
           ELSE (CASE WHEN HOUR(r.inicio) IN (7, 18) THEN 0.90 WHEN HOUR(r.inicio) IN (6, 17) THEN 0.75 ELSE 0.55 END)
                * (0.45 + 0.55 * SIN(PI() * le.orden / (t.total + 1))) + MOD(r.id * 7 + le.orden * 13, 11) / 100
       END)),
       IF(HOUR(r.inicio) = 12 AND le.orden = 1, 5, 0),
       r.inicio + INTERVAL ((le.orden - 1) * 4) MINUTE, @demo
FROM recorridos r
JOIN buses b ON b.id = r.bus_id
JOIN linea_estacion le ON le.linea_id = r.linea_id
JOIN (SELECT linea_id, MAX(orden) AS total FROM linea_estacion GROUP BY linea_id) t ON t.linea_id = r.linea_id
WHERE @hay = 0 AND r.updated_at = @demo
ORDER BY r.inicio, r.id, le.orden;

-- Alertas de baja ocupación de esas esperas, atendidas al terminar la espera.
INSERT INTO alertas (estacion_id, bus_id, linea_id, tipo, mensaje, estado, atendida_en, created_at, updated_at)
SELECT re.estacion_id, r.bus_id, r.linea_id, 'baja_ocupacion',
       CONCAT(b.codigo, ' llegó a ', e.nombre, ' con ', re.pasajeros_a_bordo, ' de ', b.capacidad_maxima, ' pasajeros (',
              ROUND(re.pasajeros_a_bordo / b.capacidad_maxima * 100), ' %): esperar 5 minutos adicionales.'),
       'atendida', re.hora_llegada + INTERVAL 5 MINUTE, re.hora_llegada, @demo
FROM recorrido_estaciones re
JOIN recorridos r ON r.id = re.recorrido_id
JOIN buses b ON b.id = r.bus_id
JOIN estaciones e ON e.id = re.estacion_id
WHERE @hay = 0 AND r.updated_at = @demo AND re.espera_extra_min = 5
ORDER BY re.hora_llegada;

-- ---------------------------------------------------------------------
-- 3. Pasajeros en espera: todas las estaciones a las 06:45 y 17:45 (por debajo de la alta demanda)
--    y un pico a las 07:40 en algunas estaciones cada día (alta demanda: 150 % de la capacidad de referencia).
--    Capacidad de referencia: el bus más grande de las líneas de la estación (como AlertaService).
-- ---------------------------------------------------------------------
INSERT INTO registros_pasajeros (estacion_id, user_id, cantidad, registrado_en, created_at, updated_at)
SELECT z.estacion_id, z.user_id, z.cantidad, z.momento, z.momento, @demo
FROM (
    SELECT e.id AS estacion_id,
           COALESCE((SELECT u.id FROM users u WHERE u.estacion_id = e.id AND u.rol = 'operador' AND u.activo = 1 ORDER BY u.id LIMIT 1), @admin) AS user_id,
           CASE WHEN s.pico = 1
                THEN CEIL(e.capacidad * 1.5) + 5 + MOD(e.id * 3 + d.d * 7, 40)
                ELSE LEAST(CEIL(e.capacidad * 1.5) - 5, 20 + MOD(e.id * 37 + d.d * 11 + s.idx * 17, 95))
           END AS cantidad,
           TIMESTAMP(DATE(@ahora) - INTERVAL d.d DAY, s.hora) AS momento,
           s.pico, d.d
    FROM (SELECT es.id, (SELECT MAX(b.capacidad_maxima) FROM buses b JOIN linea_estacion le ON le.linea_id = b.linea_id
                         WHERE le.estacion_id = es.id) AS capacidad
          FROM estaciones es WHERE es.estado = 'activa') e
    CROSS JOIN (SELECT 0 AS d UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3
                UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6) d
    CROSS JOIN (SELECT 0 AS idx, '06:45:00' AS hora, 0 AS pico UNION ALL SELECT 1, '07:40:00', 1 UNION ALL SELECT 2, '17:45:00', 0) s
    WHERE e.capacidad IS NOT NULL
) z
WHERE @hay = 0 AND z.momento <= @ahora AND (z.pico = 0 OR MOD(z.estacion_id + z.d, 7) = 0)
ORDER BY z.momento, z.estacion_id;

-- Alertas de alta demanda de esos picos: atendidas a los 15 minutos, salvo las de hoy (activas).
INSERT INTO alertas (estacion_id, bus_id, linea_id, tipo, mensaje, estado, atendida_en, created_at, updated_at)
SELECT rp.estacion_id, NULL, ref.linea_id, 'alta_demanda',
       CONCAT('Estación ', e.nombre, ' con ', rp.cantidad, ' pasajeros en espera (', ROUND(rp.cantidad / ref.capacidad * 100),
              ' % de ', ref.capacidad, '): enviar otro bus.'),
       IF(DATE(rp.registrado_en) = DATE(@ahora), 'activa', 'atendida'),
       IF(DATE(rp.registrado_en) = DATE(@ahora), NULL, rp.registrado_en + INTERVAL 15 MINUTE),
       rp.registrado_en, @demo
FROM registros_pasajeros rp
JOIN estaciones e ON e.id = rp.estacion_id
JOIN (SELECT le.estacion_id, MAX(b.capacidad_maxima) AS capacidad,
             SUBSTRING_INDEX(GROUP_CONCAT(b.linea_id ORDER BY b.capacidad_maxima DESC, b.id), ',', 1) AS linea_id
      FROM linea_estacion le JOIN buses b ON b.linea_id = le.linea_id GROUP BY le.estacion_id) ref ON ref.estacion_id = rp.estacion_id
WHERE @hay = 0 AND rp.updated_at = @demo AND rp.cantidad >= ref.capacidad * 1.5
-- No duplica una alta demanda activa de la misma estación (regla de AlertaService).
  AND NOT (DATE(rp.registrado_en) = DATE(@ahora)
           AND EXISTS (SELECT 1 FROM alertas a WHERE a.estacion_id = rp.estacion_id AND a.tipo = 'alta_demanda' AND a.estado = 'activa'))
ORDER BY rp.registrado_en;

-- Una incidencia activa en Trébol para mostrar en el panel.
INSERT INTO alertas (estacion_id, bus_id, linea_id, tipo, mensaje, estado, atendida_en, created_at, updated_at)
SELECT e.id, NULL, NULL, 'incidencia', 'Torniquete del Acceso Norte con lecturas lentas: revisar el lector.', 'activa', NULL,
       @ahora - INTERVAL 20 MINUTE, @demo
FROM estaciones e
WHERE @hay = 0 AND e.nombre = 'Trébol';

-- ---------------------------------------------------------------------
-- 4. Pasajeros y tarjetas de demostración (saldo inicial 0.00: lo suben las recargas).
-- ---------------------------------------------------------------------
INSERT INTO pasajeros (nombre, dpi, telefono, created_at, updated_at)
SELECT CONCAT(ELT(1 + MOD(n.n, 10), 'Andrea', 'Bryan', 'Carla', 'Diego', 'Elena', 'Fernando', 'Gabriela', 'Héctor', 'Isabel', 'Jorge'), ' ',
              ELT(1 + MOD(n.n * 3 + FLOOR(n.n / 10) * 5, 10), 'López', 'Pérez', 'García', 'Morales', 'Hernández', 'Castillo', 'Ramírez', 'Méndez', 'Orozco', 'Juárez'), ' ',
              ELT(1 + MOD(n.n * 7 + 3, 10), 'Aguilar', 'Barrios', 'Cifuentes', 'Estrada', 'Godínez', 'Marroquín', 'Monzón', 'Reyes', 'Sandoval', 'Velásquez')),
       CONCAT('31', LPAD(n.n, 9, '0'), '01'), CONCAT('5600', LPAD(n.n, 4, '0')),
       @ahora - INTERVAL 20 DAY, @demo
FROM (SELECT a.n * 10 + b.n + 1 AS n
      FROM (SELECT 0 AS n UNION ALL SELECT 1) a
      CROSS JOIN (SELECT 0 AS n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4
                  UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) b) n
WHERE @hay = 0 AND NOT EXISTS (SELECT 1 FROM pasajeros x WHERE x.dpi = CONCAT('31', LPAD(n.n, 9, '0'), '01'))
ORDER BY n.n;

INSERT INTO tarjetas (pasajero_id, codigo, saldo, estado, fecha_emision, created_at, updated_at)
SELECT p.id, CONCAT('TM-', LPAD(100 + CAST(SUBSTRING(p.dpi, 3, 9) AS UNSIGNED), 6, '0')), 0.00, 'activa',
       DATE(@ahora) - INTERVAL 20 DAY, @ahora - INTERVAL 20 DAY, @demo
FROM pasajeros p
WHERE @hay = 0 AND p.updated_at = @demo
  AND NOT EXISTS (SELECT 1 FROM tarjetas t WHERE t.codigo = CONCAT('TM-', LPAD(100 + CAST(SUBSTRING(p.dpi, 3, 9) AS UNSIGNED), 6, '0')))
ORDER BY p.id;

-- ---------------------------------------------------------------------
-- 5. Movimientos de las tarjetas. Primero se arman en una tabla temporal: la inserción en
--    transacciones no puede leer tarjetas, porque su trigger actualiza esa tabla.
-- ---------------------------------------------------------------------
DROP TEMPORARY TABLE IF EXISTS tm_demo_movimientos;
CREATE TEMPORARY TABLE tm_demo_movimientos (
    tarjeta_id   BIGINT UNSIGNED NOT NULL,
    acceso_id    BIGINT UNSIGNED NULL,
    estacion_id  BIGINT UNSIGNED NULL,
    user_id      BIGINT UNSIGNED NULL,
    tipo         VARCHAR(10) NOT NULL,
    metodo       VARCHAR(10) NULL,
    monto        DECIMAL(10,2) NOT NULL,
    estado       VARCHAR(10) NOT NULL,
    motivo       VARCHAR(150) NULL,
    marca        VARCHAR(20) NULL,
    ultimos4     CHAR(4) NULL,
    referencia   VARCHAR(40) NULL,
    momento      DATETIME NOT NULL,
    prioridad    TINYINT NOT NULL
);

-- 5a. Recarga inicial de Q50.00 en ventanilla hace 7 días: cubre los 14 pasajes de la semana.
INSERT INTO tm_demo_movimientos
SELECT c.id, NULL, a.estacion_id,
       COALESCE((SELECT u.id FROM users u WHERE u.estacion_id = a.estacion_id AND u.rol = 'operador' AND u.activo = 1 ORDER BY u.id LIMIT 1), @admin),
       'recarga', 'efectivo', 50.00, 'aprobada', NULL, NULL, NULL, NULL,
       TIMESTAMP(DATE(@ahora) - INTERVAL 7 DAY, '06:00:00') + INTERVAL c.n MINUTE, 0
FROM (SELECT id, ROW_NUMBER() OVER (ORDER BY id) AS n FROM tarjetas WHERE updated_at = @demo) c
JOIN (SELECT estacion_id, ROW_NUMBER() OVER (ORDER BY id) - 1 AS k, COUNT(*) OVER () AS total FROM accesos) a
  ON a.k = MOD(c.n * 3, a.total)
WHERE @hay = 0;

-- 5b. Pasajes: dos por día (mañana y tarde) en accesos que cambian según la tarjeta y el día.
INSERT INTO tm_demo_movimientos
SELECT c.id, a.id, a.estacion_id, NULL, 'pago', NULL, 1.00, 'aprobada', NULL, NULL, NULL, NULL,
       TIMESTAMP(DATE(@ahora) - INTERVAL d.d DAY, IF(s.idx = 0, '06:10:00', '17:05:00')) + INTERVAL MOD(c.n * (7 + s.idx * 4) + d.d * 3, 100) MINUTE, 1
FROM (SELECT id, ROW_NUMBER() OVER (ORDER BY id) AS n FROM tarjetas WHERE updated_at = @demo) c
CROSS JOIN (SELECT 0 AS d UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3
            UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6) d
CROSS JOIN (SELECT 0 AS idx UNION ALL SELECT 1) s
JOIN (SELECT id, estacion_id, ROW_NUMBER() OVER (ORDER BY id) - 1 AS k, COUNT(*) OVER () AS total FROM accesos) a
  ON a.k = MOD(c.n * 3 + d.d + s.idx * 5, a.total)
WHERE @hay = 0;

-- 5c. Recargas en ventanilla (Q20.00) cada tres días por tarjeta.
INSERT INTO tm_demo_movimientos
SELECT c.id, NULL, a.estacion_id,
       COALESCE((SELECT u.id FROM users u WHERE u.estacion_id = a.estacion_id AND u.rol = 'operador' AND u.activo = 1 ORDER BY u.id LIMIT 1), @admin),
       'recarga', 'efectivo', 20.00, 'aprobada', NULL, NULL, NULL, NULL,
       TIMESTAMP(DATE(@ahora) - INTERVAL d.d DAY, '07:55:00') + INTERVAL MOD(c.n * 5, 40) MINUTE, 0
FROM (SELECT id, ROW_NUMBER() OVER (ORDER BY id) AS n FROM tarjetas WHERE updated_at = @demo) c
CROSS JOIN (SELECT 0 AS d UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3
            UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6) d
JOIN (SELECT estacion_id, ROW_NUMBER() OVER (ORDER BY id) - 1 AS k, COUNT(*) OVER () AS total FROM accesos) a
  ON a.k = MOD(c.n * 3 + d.d, a.total)
WHERE @hay = 0 AND MOD(c.n + d.d, 3) = 0;

-- 5d. Recargas en línea con la pasarela simulada: aprobadas (Q25.00) y rechazadas (fondos insuficientes o vencida).
INSERT INTO tm_demo_movimientos
SELECT c.id, NULL, NULL, NULL, 'recarga', 'tarjeta', v.monto, v.estado, v.motivo, v.marca, v.ultimos4,
       CONCAT('SIM-DEMO', LPAD(c.id, 5, '0'), d.d, v.tipo),
       TIMESTAMP(DATE(@ahora) - INTERVAL d.d DAY, v.hora) + INTERVAL MOD(c.n * 3, 50) MINUTE, 0
FROM (SELECT id, ROW_NUMBER() OVER (ORDER BY id) AS n FROM tarjetas WHERE updated_at = @demo) c
CROSS JOIN (SELECT 0 AS d UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3
            UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6) d
JOIN (SELECT 'A' AS tipo, 25.00 AS monto, 'aprobada' AS estado, NULL AS motivo, 'Visa' AS marca, '4242' AS ultimos4, '20:00:00' AS hora, 4 AS cada, 1 AS resto
      UNION ALL SELECT 'M', 25.00, 'aprobada', NULL, 'Mastercard', '4444', '13:10:00', 5, 2
      UNION ALL SELECT 'F', 50.00, 'rechazada', 'Fondos insuficientes', 'Visa', '0002', '21:10:00', 9, 4
      UNION ALL SELECT 'V', 30.00, 'rechazada', 'Tarjeta vencida', 'Visa', '0069', '19:20:00', 13, 6) v
  ON MOD(c.n + d.d, v.cada) = v.resto
WHERE @hay = 0;

-- 5e. Intentos de pasaje con TM-000005 (bloqueada) y TM-000006 (suspendida): rechazados.
INSERT INTO tm_demo_movimientos
SELECT t.id, a.id, a.estacion_id, NULL, 'pago', NULL, 1.00, 'rechazada', 'La tarjeta no se encuentra activa.', NULL, NULL, NULL,
       TIMESTAMP(DATE(@ahora) - INTERVAL d.d DAY, '07:15:00') + INTERVAL t.id MINUTE, 1
FROM tarjetas t
CROSS JOIN (SELECT 1 AS d UNION ALL SELECT 3 UNION ALL SELECT 5) d
JOIN (SELECT id, estacion_id, ROW_NUMBER() OVER (ORDER BY id) - 1 AS k, COUNT(*) OVER () AS total FROM accesos) a
  ON a.k = MOD(t.id * 7 + d.d, a.total)
WHERE @hay = 0 AND t.codigo IN ('TM-000005', 'TM-000006') AND t.estado <> 'activa';

-- 5f. Inserción en orden cronológico (la recarga antes del pasaje si coinciden): los triggers calculan el saldo.
INSERT INTO transacciones (tarjeta_id, acceso_id, estacion_id, user_id, tipo, metodo, monto, estado, motivo_rechazo,
                           tarjeta_marca, tarjeta_ultimos4, referencia, created_at, updated_at)
SELECT tarjeta_id, acceso_id, estacion_id, user_id, tipo, metodo, monto, estado, motivo, marca, ultimos4, referencia, momento, @demo
FROM tm_demo_movimientos
WHERE momento <= @ahora
ORDER BY momento, prioridad, tarjeta_id;

DROP TEMPORARY TABLE IF EXISTS tm_demo_movimientos;

-- ---------------------------------------------------------------------
-- 6. Recorridos "en curso" del script inicial abiertos desde hace más de un día: se cierran
--    en su última llegada y el bus vuelve a quedar disponible si no tiene otro recorrido en curso.
-- ---------------------------------------------------------------------
UPDATE recorridos r
JOIN (SELECT recorrido_id, MAX(hora_llegada) AS ultima FROM recorrido_estaciones GROUP BY recorrido_id) v ON v.recorrido_id = r.id
SET r.estado = 'finalizado', r.fin = v.ultima + INTERVAL 1 MINUTE, r.updated_at = @demo
WHERE r.id > 0 AND @hay = 0 AND r.estado = 'en_curso' AND r.inicio < @ahora - INTERVAL 1 DAY;

UPDATE buses b
SET b.estado = 'disponible', b.updated_at = @demo
WHERE b.id > 0 AND @hay = 0 AND b.estado = 'en_ruta'
  AND NOT EXISTS (SELECT 1 FROM recorridos r WHERE r.bus_id = b.id AND r.estado = 'en_curso');

-- ---------------------------------------------------------------------
-- 7. Marca de la carga (también deja constancia en la bitácora).
-- ---------------------------------------------------------------------
INSERT INTO bitacora (user_id, accion, tabla, registro_id, detalle, created_at)
SELECT @admin, 'cargar_datos_demo', 'sistema', NULL,
       CONCAT('Datos de demostración del ', DATE_FORMAT(@ahora - INTERVAL 6 DAY, '%d/%m/%Y'), ' al ', DATE_FORMAT(@ahora, '%d/%m/%Y'),
              ' (script 07_datos_demo.sql)'),
       @ahora
WHERE @hay = 0;

COMMIT;

-- ---------------------------------------------------------------------
-- Verificación
-- ---------------------------------------------------------------------
SELECT IF(@hay = 0, 'Datos de demostración cargados', 'Ya estaban cargados: no se hizo nada') AS resultado;
SELECT 'recorridos' AS tabla, COUNT(*) AS filas FROM recorridos WHERE updated_at = @demo
UNION ALL SELECT 'llegadas', COUNT(*) FROM recorrido_estaciones WHERE updated_at = @demo
UNION ALL SELECT 'pasajeros en espera', COUNT(*) FROM registros_pasajeros WHERE updated_at = @demo
UNION ALL SELECT 'alertas', COUNT(*) FROM alertas WHERE updated_at = @demo
UNION ALL SELECT 'alertas activas', COUNT(*) FROM alertas WHERE updated_at = @demo AND estado = 'activa'
UNION ALL SELECT 'pasajeros', COUNT(*) FROM pasajeros WHERE updated_at = @demo
UNION ALL SELECT 'tarjetas', COUNT(*) FROM tarjetas WHERE updated_at = @demo
UNION ALL SELECT 'transacciones', COUNT(*) FROM transacciones WHERE updated_at = @demo
UNION ALL SELECT 'transacciones rechazadas', COUNT(*) FROM transacciones WHERE updated_at = @demo AND estado = 'rechazada';
SELECT codigo, saldo FROM tarjetas WHERE codigo BETWEEN 'TM-000101' AND 'TM-000120' ORDER BY codigo;
