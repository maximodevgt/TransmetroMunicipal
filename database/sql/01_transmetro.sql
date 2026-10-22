-- =====================================================================
-- Sistema Web de Control y Administración de Transmetro
-- Script completo de base de datos - MySQL 8.0.16+
-- Autor: Maximiliano Ajca Us
--
-- Contenido:
--   SECCIÓN 1. Base de datos y tablas (19)
--   SECCIÓN 2. Vista de resumen por línea
--   SECCIÓN 3. Triggers de reglas de negocio (4)
--   SECCIÓN 4. Datos iniciales
-- =====================================================================

-- =====================================================================
-- SECCIÓN 1. BASE DE DATOS Y TABLAS
-- =====================================================================
DROP DATABASE IF EXISTS transmetro;
CREATE DATABASE transmetro CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE transmetro;

-- ---------------------------------------------------------------------
-- Tabla 1. Municipalidades
-- ---------------------------------------------------------------------
CREATE TABLE municipalidades (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre        VARCHAR(100) NOT NULL,
  departamento  VARCHAR(100) NOT NULL,
  created_at    TIMESTAMP NULL,
  updated_at    TIMESTAMP NULL,
  UNIQUE KEY uk_municipalidades_nombre (nombre)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Tabla 2. Parqueos
-- ---------------------------------------------------------------------
CREATE TABLE parqueos (
  id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre     VARCHAR(100) NOT NULL,
  direccion  VARCHAR(200) NULL,
  capacidad  INT UNSIGNED NOT NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  UNIQUE KEY uk_parqueos_nombre (nombre),
  CONSTRAINT chk_parqueos_capacidad CHECK (capacidad > 0)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Tabla 3. Pilotos
-- ---------------------------------------------------------------------
CREATE TABLE pilotos (
  id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre     VARCHAR(120) NOT NULL,
  dpi        VARCHAR(13)  NOT NULL,
  licencia   VARCHAR(20)  NOT NULL,
  residencia VARCHAR(200) NOT NULL,
  telefono   VARCHAR(15)  NOT NULL,
  email      VARCHAR(150) NULL,
  estado     ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  UNIQUE KEY uk_pilotos_dpi (dpi),
  UNIQUE KEY uk_pilotos_licencia (licencia)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Tabla 4. Pasajeros (titulares de tarjeta)
-- ---------------------------------------------------------------------
CREATE TABLE pasajeros (
  id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre     VARCHAR(120) NOT NULL,
  dpi        VARCHAR(13)  NOT NULL,
  telefono   VARCHAR(15)  NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  UNIQUE KEY uk_pasajeros_dpi (dpi)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Tabla 5. Líneas
-- ---------------------------------------------------------------------
CREATE TABLE lineas (
  id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  municipalidad_id BIGINT UNSIGNED NOT NULL,
  codigo           VARCHAR(10)  NOT NULL,
  nombre           VARCHAR(100) NOT NULL,
  estado           ENUM('activa','inactiva') NOT NULL DEFAULT 'activa',
  created_at       TIMESTAMP NULL,
  updated_at       TIMESTAMP NULL,
  UNIQUE KEY uk_lineas_codigo (codigo),
  CONSTRAINT fk_lineas_municipalidad FOREIGN KEY (municipalidad_id)
    REFERENCES municipalidades(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Tabla 6. Estaciones
-- ---------------------------------------------------------------------
CREATE TABLE estaciones (
  id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  municipalidad_id BIGINT UNSIGNED NOT NULL,
  nombre           VARCHAR(100) NOT NULL,
  direccion        VARCHAR(200) NULL,
  latitud          DECIMAL(9,6) NULL,
  longitud         DECIMAL(9,6) NULL,
  estado           ENUM('activa','inactiva') NOT NULL DEFAULT 'activa',
  created_at       TIMESTAMP NULL,
  updated_at       TIMESTAMP NULL,
  UNIQUE KEY uk_estaciones_nombre (nombre),
  CONSTRAINT chk_estaciones_latitud  CHECK (latitud  IS NULL OR latitud  BETWEEN -90  AND 90),
  CONSTRAINT chk_estaciones_longitud CHECK (longitud IS NULL OR longitud BETWEEN -180 AND 180),
  CONSTRAINT fk_estaciones_municipalidad FOREIGN KEY (municipalidad_id)
    REFERENCES municipalidades(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Tabla 7. Línea - Estación (orden del recorrido y distancias)
-- ---------------------------------------------------------------------
CREATE TABLE linea_estacion (
  id                    BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  linea_id              BIGINT UNSIGNED NOT NULL,
  estacion_id           BIGINT UNSIGNED NOT NULL,
  orden                 INT UNSIGNED NOT NULL,
  distancia_anterior_km DECIMAL(6,2) NOT NULL DEFAULT 0.00,
  created_at            TIMESTAMP NULL,
  updated_at            TIMESTAMP NULL,
  UNIQUE KEY uk_linea_estacion (linea_id, estacion_id),
  UNIQUE KEY uk_linea_orden (linea_id, orden),
  CONSTRAINT chk_linea_estacion_orden CHECK (orden >= 1),
  CONSTRAINT chk_linea_estacion_distancia CHECK (distancia_anterior_km >= 0),
  CONSTRAINT fk_le_linea FOREIGN KEY (linea_id)
    REFERENCES lineas(id) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_le_estacion FOREIGN KEY (estacion_id)
    REFERENCES estaciones(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Tabla 8. Accesos
-- ---------------------------------------------------------------------
CREATE TABLE accesos (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  estacion_id BIGINT UNSIGNED NOT NULL,
  nombre      VARCHAR(100) NOT NULL,
  created_at  TIMESTAMP NULL,
  updated_at  TIMESTAMP NULL,
  UNIQUE KEY uk_accesos_estacion_nombre (estacion_id, nombre),
  CONSTRAINT fk_accesos_estacion FOREIGN KEY (estacion_id)
    REFERENCES estaciones(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Tabla 9. Guardias de seguridad
-- ---------------------------------------------------------------------
CREATE TABLE guardias (
  id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  acceso_id  BIGINT UNSIGNED NOT NULL,
  nombre     VARCHAR(120) NOT NULL,
  dpi        VARCHAR(13)  NOT NULL,
  telefono   VARCHAR(15)  NULL,
  turno      ENUM('matutino','vespertino','nocturno') NOT NULL DEFAULT 'matutino',
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  UNIQUE KEY uk_guardias_dpi (dpi),
  CONSTRAINT fk_guardias_acceso FOREIGN KEY (acceso_id)
    REFERENCES accesos(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Tabla 10. Usuarios del sistema (administradores y operadores)
-- ---------------------------------------------------------------------
CREATE TABLE users (
  id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  estacion_id    BIGINT UNSIGNED NULL,
  name           VARCHAR(120) NOT NULL,
  email          VARCHAR(150) NOT NULL,
  password       VARCHAR(255) NOT NULL,
  rol            ENUM('administrador','operador') NOT NULL DEFAULT 'operador',
  activo         TINYINT(1) NOT NULL DEFAULT 1,
  remember_token VARCHAR(100) NULL,
  created_at     TIMESTAMP NULL,
  updated_at     TIMESTAMP NULL,
  UNIQUE KEY uk_users_email (email),
  CONSTRAINT fk_users_estacion FOREIGN KEY (estacion_id)
    REFERENCES estaciones(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Tabla 11. Buses (línea opcional, parqueo obligatorio)
-- ---------------------------------------------------------------------
CREATE TABLE buses (
  id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  linea_id         BIGINT UNSIGNED NULL,
  parqueo_id       BIGINT UNSIGNED NOT NULL,
  placa            VARCHAR(10) NOT NULL,
  codigo           VARCHAR(20) NOT NULL,
  capacidad_maxima INT UNSIGNED NOT NULL,
  estado           ENUM('disponible','en_ruta','mantenimiento','inactivo') NOT NULL DEFAULT 'disponible',
  created_at       TIMESTAMP NULL,
  updated_at       TIMESTAMP NULL,
  UNIQUE KEY uk_buses_placa (placa),
  UNIQUE KEY uk_buses_codigo (codigo),
  KEY idx_buses_linea (linea_id),
  CONSTRAINT chk_buses_capacidad CHECK (capacidad_maxima > 0),
  CONSTRAINT fk_buses_linea FOREIGN KEY (linea_id)
    REFERENCES lineas(id) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_buses_parqueo FOREIGN KEY (parqueo_id)
    REFERENCES parqueos(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Tabla 12. Historial educativo del piloto
-- ---------------------------------------------------------------------
CREATE TABLE historial_educativo (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  piloto_id   BIGINT UNSIGNED NOT NULL,
  nivel       ENUM('primaria','basico','diversificado','universitario','otro') NOT NULL,
  institucion VARCHAR(150) NOT NULL,
  titulo      VARCHAR(150) NULL,
  anio        YEAR NULL,
  created_at  TIMESTAMP NULL,
  updated_at  TIMESTAMP NULL,
  CONSTRAINT fk_historial_piloto FOREIGN KEY (piloto_id)
    REFERENCES pilotos(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Tabla 13. Recorridos
-- ---------------------------------------------------------------------
CREATE TABLE recorridos (
  id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  linea_id   BIGINT UNSIGNED NOT NULL,
  bus_id     BIGINT UNSIGNED NOT NULL,
  piloto_id  BIGINT UNSIGNED NOT NULL,
  inicio     DATETIME NOT NULL,
  fin        DATETIME NULL,
  estado     ENUM('en_curso','finalizado','cancelado') NOT NULL DEFAULT 'en_curso',
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  KEY idx_recorridos_estado (estado),
  CONSTRAINT fk_recorridos_linea FOREIGN KEY (linea_id)
    REFERENCES lineas(id) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_recorridos_bus FOREIGN KEY (bus_id)
    REFERENCES buses(id) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_recorridos_piloto FOREIGN KEY (piloto_id)
    REFERENCES pilotos(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Tabla 14. Estaciones visitadas en cada recorrido
-- ---------------------------------------------------------------------
CREATE TABLE recorrido_estaciones (
  id                BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  recorrido_id      BIGINT UNSIGNED NOT NULL,
  estacion_id       BIGINT UNSIGNED NOT NULL,
  hora_llegada      DATETIME NOT NULL,
  hora_salida       DATETIME NULL,
  pasajeros_a_bordo INT UNSIGNED NOT NULL DEFAULT 0,
  espera_extra_min  TINYINT UNSIGNED NOT NULL DEFAULT 0,
  created_at        TIMESTAMP NULL,
  updated_at        TIMESTAMP NULL,
  KEY idx_re_recorrido (recorrido_id, hora_llegada),
  CONSTRAINT fk_re_recorrido FOREIGN KEY (recorrido_id)
    REFERENCES recorridos(id) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_re_estacion FOREIGN KEY (estacion_id)
    REFERENCES estaciones(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Tabla 15. Registro de pasajeros en espera por estación
-- ---------------------------------------------------------------------
CREATE TABLE registros_pasajeros (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  estacion_id   BIGINT UNSIGNED NOT NULL,
  user_id       BIGINT UNSIGNED NOT NULL,
  cantidad      INT UNSIGNED NOT NULL,
  registrado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at    TIMESTAMP NULL,
  updated_at    TIMESTAMP NULL,
  KEY idx_rp_estacion_fecha (estacion_id, registrado_en),
  CONSTRAINT fk_rp_estacion FOREIGN KEY (estacion_id)
    REFERENCES estaciones(id) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_rp_user FOREIGN KEY (user_id)
    REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Tabla 16. Alertas
-- ---------------------------------------------------------------------
CREATE TABLE alertas (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  estacion_id BIGINT UNSIGNED NULL,
  bus_id      BIGINT UNSIGNED NULL,
  linea_id    BIGINT UNSIGNED NULL,
  tipo        ENUM('alta_demanda','baja_ocupacion','asignacion','insuficiencia_unidades','incidencia') NOT NULL,
  mensaje     VARCHAR(255) NOT NULL,
  estado      ENUM('activa','atendida') NOT NULL DEFAULT 'activa',
  atendida_en DATETIME NULL,
  created_at  TIMESTAMP NULL,
  updated_at  TIMESTAMP NULL,
  KEY idx_alertas_estado_tipo (estado, tipo),
  CONSTRAINT fk_alertas_estacion FOREIGN KEY (estacion_id)
    REFERENCES estaciones(id) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_alertas_bus FOREIGN KEY (bus_id)
    REFERENCES buses(id) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_alertas_linea FOREIGN KEY (linea_id)
    REFERENCES lineas(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Tabla 17. Tarjetas Transmetro
-- ---------------------------------------------------------------------
CREATE TABLE tarjetas (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  pasajero_id   BIGINT UNSIGNED NOT NULL,
  codigo        VARCHAR(20) NOT NULL,
  saldo         DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  estado        ENUM('activa','bloqueada','suspendida','vencida') NOT NULL DEFAULT 'activa',
  fecha_emision DATE NOT NULL,
  created_at    TIMESTAMP NULL,
  updated_at    TIMESTAMP NULL,
  UNIQUE KEY uk_tarjetas_codigo (codigo),
  CONSTRAINT chk_tarjetas_saldo CHECK (saldo >= 0),
  CONSTRAINT fk_tarjetas_pasajero FOREIGN KEY (pasajero_id)
    REFERENCES pasajeros(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Tabla 18. Transacciones (recargas y pagos)
-- ---------------------------------------------------------------------
CREATE TABLE transacciones (
  id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tarjeta_id       BIGINT UNSIGNED NOT NULL,
  acceso_id        BIGINT UNSIGNED NULL,
  estacion_id      BIGINT UNSIGNED NULL,
  user_id          BIGINT UNSIGNED NULL,
  tipo             ENUM('recarga','pago') NOT NULL,
  metodo           ENUM('efectivo','tarjeta') NULL,
  monto            DECIMAL(10,2) NOT NULL,
  saldo_resultante DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  estado           ENUM('aprobada','rechazada') NOT NULL DEFAULT 'aprobada',
  motivo_rechazo   VARCHAR(150) NULL,
  -- Pasarela SIMULADA: solo marca, últimos 4 dígitos y referencia inventada; nunca el número completo ni el CVV.
  tarjeta_marca      VARCHAR(20) NULL,
  tarjeta_ultimos4   CHAR(4) NULL,
  referencia         VARCHAR(40) NULL,
  llave_idempotencia VARCHAR(64) NULL,
  created_at       TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP NULL,
  KEY idx_transacciones_tarjeta_fecha (tarjeta_id, created_at),
  CONSTRAINT uk_transacciones_llave UNIQUE (llave_idempotencia),
  CONSTRAINT chk_transacciones_monto CHECK (monto > 0),
  CONSTRAINT chk_transacciones_ultimos4 CHECK (tarjeta_ultimos4 IS NULL OR tarjeta_ultimos4 REGEXP '^[0-9]{4}$'),
  CONSTRAINT fk_transacciones_tarjeta FOREIGN KEY (tarjeta_id)
    REFERENCES tarjetas(id) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_transacciones_acceso FOREIGN KEY (acceso_id)
    REFERENCES accesos(id) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_transacciones_estacion FOREIGN KEY (estacion_id)
    REFERENCES estaciones(id) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_transacciones_user FOREIGN KEY (user_id)
    REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Tabla 19. Bitácora de operaciones
-- ---------------------------------------------------------------------
CREATE TABLE bitacora (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     BIGINT UNSIGNED NOT NULL,
  accion      VARCHAR(50)  NOT NULL,
  tabla       VARCHAR(50)  NOT NULL,
  registro_id BIGINT UNSIGNED NULL,
  detalle     VARCHAR(255) NULL,
  created_at  TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_bitacora_tabla (tabla, registro_id),
  CONSTRAINT fk_bitacora_user FOREIGN KEY (user_id)
    REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- =====================================================================
-- SECCIÓN 2. VISTA DE RESUMEN POR LÍNEA
-- Estaciones, distancia total, buses asignados, mínimo y máximo
-- =====================================================================
CREATE OR REPLACE VIEW vw_resumen_lineas AS
SELECT
  l.id,
  l.codigo,
  l.nombre,
  (SELECT COUNT(*) FROM linea_estacion le WHERE le.linea_id = l.id)       AS total_estaciones,
  (SELECT COALESCE(SUM(le.distancia_anterior_km), 0)
     FROM linea_estacion le WHERE le.linea_id = l.id)                     AS distancia_total_km,
  (SELECT COUNT(*) FROM buses b WHERE b.linea_id = l.id)                  AS buses_asignados,
  (SELECT COUNT(*) FROM linea_estacion le WHERE le.linea_id = l.id)       AS buses_minimos,
  (SELECT COUNT(*) * 2 FROM linea_estacion le WHERE le.linea_id = l.id)   AS buses_maximos
FROM lineas l;

-- =====================================================================
-- SECCIÓN 3. TRIGGERS DE REGLAS DE NEGOCIO
-- =====================================================================
DELIMITER $$

-- Máximo de buses por línea = estaciones × 2 (al registrar un bus)
CREATE TRIGGER trg_buses_max_insert
BEFORE INSERT ON buses
FOR EACH ROW
BEGIN
  DECLARE v_estaciones INT DEFAULT 0;
  DECLARE v_buses INT DEFAULT 0;
  IF NEW.linea_id IS NOT NULL THEN
    SELECT COUNT(*) INTO v_estaciones FROM linea_estacion WHERE linea_id = NEW.linea_id;
    SELECT COUNT(*) INTO v_buses FROM buses WHERE linea_id = NEW.linea_id;
    IF v_buses >= v_estaciones * 2 THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'La línea ya tiene el máximo de buses permitido (estaciones × 2).';
    END IF;
  END IF;
END$$

-- Máximo de buses por línea = estaciones × 2 (al reasignar un bus)
CREATE TRIGGER trg_buses_max_update
BEFORE UPDATE ON buses
FOR EACH ROW
BEGIN
  DECLARE v_estaciones INT DEFAULT 0;
  DECLARE v_buses INT DEFAULT 0;
  IF NEW.linea_id IS NOT NULL AND NOT (NEW.linea_id <=> OLD.linea_id) THEN
    SELECT COUNT(*) INTO v_estaciones FROM linea_estacion WHERE linea_id = NEW.linea_id;
    SELECT COUNT(*) INTO v_buses FROM buses WHERE linea_id = NEW.linea_id;
    IF v_buses >= v_estaciones * 2 THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'La línea ya tiene el máximo de buses permitido (estaciones × 2).';
    END IF;
  END IF;
END$$

-- Validar tarjeta activa y saldo antes de registrar la transacción
CREATE TRIGGER trg_transacciones_validar
BEFORE INSERT ON transacciones
FOR EACH ROW
BEGIN
  DECLARE v_estado VARCHAR(20);
  DECLARE v_saldo DECIMAL(10,2);
  SELECT estado, saldo INTO v_estado, v_saldo
    FROM tarjetas WHERE id = NEW.tarjeta_id FOR UPDATE;

  IF NEW.estado = 'aprobada' THEN
    IF v_estado <> 'activa' THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'La tarjeta no se encuentra activa.';
    END IF;
    IF NEW.tipo = 'pago' AND v_saldo < NEW.monto THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Saldo insuficiente en la tarjeta.';
    END IF;
    IF NEW.tipo = 'pago' THEN
      SET NEW.saldo_resultante = v_saldo - NEW.monto;
    ELSE
      SET NEW.saldo_resultante = v_saldo + NEW.monto;
    END IF;
  ELSE
    SET NEW.saldo_resultante = v_saldo;
  END IF;
END$$

-- Actualizar el saldo de la tarjeta al aprobar la transacción
CREATE TRIGGER trg_transacciones_saldo
AFTER INSERT ON transacciones
FOR EACH ROW
BEGIN
  IF NEW.estado = 'aprobada' THEN
    UPDATE tarjetas SET saldo = NEW.saldo_resultante WHERE id = NEW.tarjeta_id;
  END IF;
END$$

DELIMITER ;

-- =====================================================================
-- SECCIÓN 4. DATOS INICIALES
-- Líneas, estaciones y tarifa (Q1.00): datos públicos del Transmetro.
-- Distancias entre estaciones: aproximadas.
-- Personas, placas, parqueos y tarjetas: datos de ejemplo.
-- Usuarios: contraseña inicial 'Transmetro2026' (hash bcrypt).
-- =====================================================================
-- ---------------------------------------------------------------------
-- Datos 1. Municipalidades
-- ---------------------------------------------------------------------
INSERT INTO municipalidades (id, nombre, departamento, created_at, updated_at) VALUES
  (1, 'Guatemala', 'Guatemala', NOW(), NOW()),
  (2, 'Villa Nueva', 'Guatemala', NOW(), NOW()),
  (3, 'Mixco', 'Guatemala', NOW(), NOW());

-- ---------------------------------------------------------------------
-- Datos 2. Líneas
-- ---------------------------------------------------------------------
INSERT INTO lineas (id, municipalidad_id, codigo, nombre, estado, created_at, updated_at) VALUES
  (1, 1, 'L12', 'Línea 12 - Eje Sur', 'activa', NOW(), NOW()),
  (2, 1, 'L1',  'Línea 1 - Centro Histórico', 'activa', NOW(), NOW()),
  (3, 1, 'L2',  'Línea 2 - Hipódromo', 'activa', NOW(), NOW());

-- ---------------------------------------------------------------------
-- Datos 3. Estaciones (San Sebastián pertenece a L1 y L2)
-- ---------------------------------------------------------------------
INSERT INTO estaciones (id, municipalidad_id, nombre, direccion, estado, created_at, updated_at) VALUES
  (1, 2, 'Centra Sur', 'Zona 12, Villa Nueva', 'activa', NOW(), NOW()),
  (2, 1, 'Monte María', 'Zona 12, Ciudad de Guatemala', 'activa', NOW(), NOW()),
  (3, 1, 'Javier', 'Zona 12, Ciudad de Guatemala', 'activa', NOW(), NOW()),
  (4, 1, 'Las Charcas', 'Zona 12, Ciudad de Guatemala', 'activa', NOW(), NOW()),
  (5, 1, 'El Carmen', 'Zona 12, Ciudad de Guatemala', 'activa', NOW(), NOW()),
  (6, 1, 'Reformita', 'Zona 12, Ciudad de Guatemala', 'activa', NOW(), NOW()),
  (7, 1, 'Mariscal', 'Zona 11, Ciudad de Guatemala', 'activa', NOW(), NOW()),
  (8, 1, 'Trébol', 'Zona 8, Ciudad de Guatemala', 'activa', NOW(), NOW()),
  (9, 1, 'Santa Cecilia', 'Zona 8, Ciudad de Guatemala', 'activa', NOW(), NOW()),
  (10, 1, 'Bolívar', 'Zona 8, Ciudad de Guatemala', 'activa', NOW(), NOW()),
  (11, 1, 'Don Bosco', 'Zona 8, Ciudad de Guatemala', 'activa', NOW(), NOW()),
  (12, 1, 'Plaza Municipal', 'Zona 1, Ciudad de Guatemala', 'activa', NOW(), NOW()),
  (13, 1, 'Plaza Barrios', 'Zona 1, Ciudad de Guatemala', 'activa', NOW(), NOW()),
  (14, 1, 'Plaza El Amate', 'Zona 1, Ciudad de Guatemala', 'activa', NOW(), NOW()),
  (15, 1, 'Gómez Carrillo', 'Zona 1, Ciudad de Guatemala', 'activa', NOW(), NOW()),
  (16, 1, 'San Agustín', 'Zona 1, Ciudad de Guatemala', 'activa', NOW(), NOW()),
  (17, 1, 'Parque Centenario', 'Zona 1, Ciudad de Guatemala', 'activa', NOW(), NOW()),
  (18, 1, 'San Sebastián', 'Zona 1, Ciudad de Guatemala', 'activa', NOW(), NOW()),
  (19, 1, 'Mercado Central', 'Zona 1, Ciudad de Guatemala', 'activa', NOW(), NOW()),
  (20, 1, 'Correos', 'Zona 1, Ciudad de Guatemala', 'activa', NOW(), NOW()),
  (21, 1, 'Beatas de Belén', 'Zona 1, Ciudad de Guatemala', 'activa', NOW(), NOW()),
  (22, 1, 'Paseo de las Letras', 'Zona 1, Ciudad de Guatemala', 'activa', NOW(), NOW()),
  (23, 1, 'Centro Cívico', 'Zona 1, Ciudad de Guatemala', 'activa', NOW(), NOW()),
  (24, 1, 'Sur 2', 'Zona 1, Ciudad de Guatemala', 'activa', NOW(), NOW()),
  (25, 1, 'Simeón Cañas', 'Zona 2, Ciudad de Guatemala', 'activa', NOW(), NOW()),
  (26, 1, 'Hipódromo del Norte', 'Zona 2, Ciudad de Guatemala', 'activa', NOW(), NOW()),
  (27, 1, 'San José de la Montaña', 'Zona 2, Ciudad de Guatemala', 'activa', NOW(), NOW()),
  (28, 1, 'Jocotenango', 'Zona 2, Ciudad de Guatemala', 'activa', NOW(), NOW());

-- ---------------------------------------------------------------------
-- Datos 3b. Coordenadas APROXIMADAS de las estaciones para el mapa (ver 04_mapa.sql)
-- ---------------------------------------------------------------------
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

-- ---------------------------------------------------------------------
-- Datos 4. Orden de estaciones por línea y distancia desde la estación anterior (km, aproximada)
-- ---------------------------------------------------------------------
INSERT INTO linea_estacion (linea_id, estacion_id, orden, distancia_anterior_km, created_at, updated_at) VALUES
  (1, 1, 1, 0.00, NOW(), NOW()),
  (1, 2, 2, 1.10, NOW(), NOW()),
  (1, 3, 3, 0.90, NOW(), NOW()),
  (1, 4, 4, 0.80, NOW(), NOW()),
  (1, 5, 5, 0.90, NOW(), NOW()),
  (1, 6, 6, 0.80, NOW(), NOW()),
  (1, 7, 7, 0.70, NOW(), NOW()),
  (1, 8, 8, 1.00, NOW(), NOW()),
  (1, 9, 9, 0.60, NOW(), NOW()),
  (1, 10, 10, 0.60, NOW(), NOW()),
  (1, 11, 11, 0.50, NOW(), NOW()),
  (1, 12, 12, 0.70, NOW(), NOW()),
  (1, 13, 13, 0.40, NOW(), NOW()),
  (1, 14, 14, 0.40, NOW(), NOW()),
  (2, 15, 1, 0.00, NOW(), NOW()),
  (2, 16, 2, 0.40, NOW(), NOW()),
  (2, 17, 3, 0.40, NOW(), NOW()),
  (2, 18, 4, 0.50, NOW(), NOW()),
  (2, 19, 5, 0.50, NOW(), NOW()),
  (2, 20, 6, 0.40, NOW(), NOW()),
  (2, 21, 7, 0.40, NOW(), NOW()),
  (2, 22, 8, 0.40, NOW(), NOW()),
  (2, 23, 9, 0.50, NOW(), NOW()),
  (2, 24, 10, 0.40, NOW(), NOW()),
  (3, 18, 1, 0.00, NOW(), NOW()),
  (3, 25, 2, 0.60, NOW(), NOW()),
  (3, 26, 3, 0.80, NOW(), NOW()),
  (3, 27, 4, 0.60, NOW(), NOW()),
  (3, 28, 5, 0.50, NOW(), NOW());

-- ---------------------------------------------------------------------
-- Datos 5. Accesos (2 por estación en L12, 1 en las demás)
-- ---------------------------------------------------------------------
INSERT INTO accesos (id, estacion_id, nombre, created_at, updated_at) VALUES
  (1, 1, 'Acceso Norte', NOW(), NOW()),
  (2, 1, 'Acceso Sur', NOW(), NOW()),
  (3, 2, 'Acceso Norte', NOW(), NOW()),
  (4, 2, 'Acceso Sur', NOW(), NOW()),
  (5, 3, 'Acceso Norte', NOW(), NOW()),
  (6, 3, 'Acceso Sur', NOW(), NOW()),
  (7, 4, 'Acceso Norte', NOW(), NOW()),
  (8, 4, 'Acceso Sur', NOW(), NOW()),
  (9, 5, 'Acceso Norte', NOW(), NOW()),
  (10, 5, 'Acceso Sur', NOW(), NOW()),
  (11, 6, 'Acceso Norte', NOW(), NOW()),
  (12, 6, 'Acceso Sur', NOW(), NOW()),
  (13, 7, 'Acceso Norte', NOW(), NOW()),
  (14, 7, 'Acceso Sur', NOW(), NOW()),
  (15, 8, 'Acceso Norte', NOW(), NOW()),
  (16, 8, 'Acceso Sur', NOW(), NOW()),
  (17, 9, 'Acceso Norte', NOW(), NOW()),
  (18, 9, 'Acceso Sur', NOW(), NOW()),
  (19, 10, 'Acceso Norte', NOW(), NOW()),
  (20, 10, 'Acceso Sur', NOW(), NOW()),
  (21, 11, 'Acceso Norte', NOW(), NOW()),
  (22, 11, 'Acceso Sur', NOW(), NOW()),
  (23, 12, 'Acceso Norte', NOW(), NOW()),
  (24, 12, 'Acceso Sur', NOW(), NOW()),
  (25, 13, 'Acceso Norte', NOW(), NOW()),
  (26, 13, 'Acceso Sur', NOW(), NOW()),
  (27, 14, 'Acceso Norte', NOW(), NOW()),
  (28, 14, 'Acceso Sur', NOW(), NOW()),
  (29, 15, 'Acceso Principal', NOW(), NOW()),
  (30, 16, 'Acceso Principal', NOW(), NOW()),
  (31, 17, 'Acceso Principal', NOW(), NOW()),
  (32, 18, 'Acceso Principal', NOW(), NOW()),
  (33, 19, 'Acceso Principal', NOW(), NOW()),
  (34, 20, 'Acceso Principal', NOW(), NOW()),
  (35, 21, 'Acceso Principal', NOW(), NOW()),
  (36, 22, 'Acceso Principal', NOW(), NOW()),
  (37, 23, 'Acceso Principal', NOW(), NOW()),
  (38, 24, 'Acceso Principal', NOW(), NOW()),
  (39, 25, 'Acceso Principal', NOW(), NOW()),
  (40, 26, 'Acceso Principal', NOW(), NOW()),
  (41, 27, 'Acceso Principal', NOW(), NOW()),
  (42, 28, 'Acceso Principal', NOW(), NOW());

-- ---------------------------------------------------------------------
-- Datos 6. Guardias (al menos uno por acceso, RN-11)
-- ---------------------------------------------------------------------
INSERT INTO guardias (acceso_id, nombre, dpi, telefono, turno, created_at, updated_at) VALUES
  (1, 'Carlos Pérez Hernández', '2000007910101', '55004177', 'vespertino', NOW(), NOW()),
  (2, 'Byron Ramírez Monzón', '2000015830101', '55008354', 'matutino', NOW(), NOW()),
  (3, 'Rolando Cifuentes López', '2000023750101', '55012531', 'vespertino', NOW(), NOW()),
  (4, 'Ana Aguilar Chávez', '2000031670101', '55016708', 'matutino', NOW(), NOW()),
  (5, 'Ingrid Marroquín Sandoval', '2000039590101', '55020885', 'vespertino', NOW(), NOW()),
  (6, 'Pedro López Cifuentes', '2000047510101', '55025062', 'matutino', NOW(), NOW()),
  (7, 'Erick Castillo Godínez', '2000055430101', '55029239', 'vespertino', NOW(), NOW()),
  (8, 'Rony Barrios Ruiz', '2000063350101', '55033416', 'matutino', NOW(), NOW()),
  (9, 'Gloria Velásquez Reyes', '2000071270101', '55037593', 'vespertino', NOW(), NOW()),
  (10, 'Mario Godínez Castillo', '2000079190101', '55041770', 'matutino', NOW(), NOW()),
  (11, 'Julio García Orozco', '2000087110101', '55045947', 'vespertino', NOW(), NOW()),
  (12, 'Nery Gómez Morales', '2000095030101', '55050124', 'matutino', NOW(), NOW()),
  (13, 'Karla Chávez Aguilar', '2000102950101', '55054301', 'vespertino', NOW(), NOW()),
  (14, 'Luis Orozco García', '2000110870101', '55058478', 'matutino', NOW(), NOW()),
  (15, 'Oscar Cabrera Estrada', '2000118780101', '55062655', 'vespertino', NOW(), NOW()),
  (16, 'Hugo Hernández Pérez', '2000126700101', '55066832', 'matutino', NOW(), NOW()),
  (17, 'Claudia Ruiz Barrios', '2000134620101', '55071009', 'vespertino', NOW(), NOW()),
  (18, 'Silvia Estrada Cabrera', '2000142540101', '55075186', 'matutino', NOW(), NOW()),
  (19, 'Edgar Juárez Méndez', '2000150460101', '55079363', 'vespertino', NOW(), NOW()),
  (20, 'Walter Sandoval Marroquín', '2000158380101', '55083540', 'matutino', NOW(), NOW()),
  (21, 'María Morales Gómez', '2000166300101', '55087717', 'vespertino', NOW(), NOW()),
  (22, 'Wendy Méndez Juárez', '2000174220101', '55091894', 'matutino', NOW(), NOW()),
  (23, 'Juan Monzón Ramírez', '2000182140101', '55096071', 'vespertino', NOW(), NOW()),
  (24, 'Sergio Reyes Velásquez', '2000190060101', '55100248', 'matutino', NOW(), NOW()),
  (25, 'Otto Pérez Hernández', '2000197980101', '55104425', 'vespertino', NOW(), NOW()),
  (26, 'Lucía Ramírez Monzón', '2000205900101', '55108602', 'matutino', NOW(), NOW()),
  (27, 'José Cifuentes López', '2000213820101', '55112779', 'vespertino', NOW(), NOW()),
  (28, 'Marvin Aguilar Chávez', '2000221740101', '55116956', 'matutino', NOW(), NOW()),
  (29, 'Estuardo Marroquín Sandoval', '2000229660101', '55121133', 'vespertino', NOW(), NOW()),
  (30, 'Sandra López Cifuentes', '2000237570101', '55125310', 'matutino', NOW(), NOW()),
  (31, 'Carlos Castillo Godínez', '2000245490101', '55129487', 'vespertino', NOW(), NOW()),
  (32, 'Byron Barrios Ruiz', '2000253410101', '55133664', 'matutino', NOW(), NOW()),
  (33, 'Rolando Velásquez Reyes', '2000261330101', '55137841', 'vespertino', NOW(), NOW()),
  (34, 'Ana Godínez Castillo', '2000269250101', '55142018', 'matutino', NOW(), NOW()),
  (35, 'Ingrid García Orozco', '2000277170101', '55146195', 'vespertino', NOW(), NOW()),
  (36, 'Pedro Gómez Morales', '2000285090101', '55150372', 'matutino', NOW(), NOW()),
  (37, 'Erick Chávez Aguilar', '2000293010101', '55154549', 'vespertino', NOW(), NOW()),
  (38, 'Rony Orozco García', '2000300930101', '55158726', 'matutino', NOW(), NOW()),
  (39, 'Gloria Cabrera Estrada', '2000308850101', '55162903', 'vespertino', NOW(), NOW()),
  (40, 'Mario Hernández Pérez', '2000316770101', '55167080', 'matutino', NOW(), NOW()),
  (41, 'Julio Ruiz Barrios', '2000324690101', '55171257', 'vespertino', NOW(), NOW()),
  (42, 'Nery Estrada Cabrera', '2000332610101', '55175434', 'matutino', NOW(), NOW());

-- ---------------------------------------------------------------------
-- Datos 7. Usuarios: 1 administrador y 1 operador por estación (RN-17)
-- ---------------------------------------------------------------------
INSERT INTO users (id, estacion_id, name, email, password, rol, activo, created_at, updated_at) VALUES
  (1, NULL, 'Administrador Municipal', 'admin@transmetro.gt', '$2y$12$67d9LEWnOnWRiG3WybSXL.LTxIlda590Byg32oAX/41YUNg9Qs7xW', 'administrador', 1, NOW(), NOW()),
  (2, 1, 'Karla Juárez Méndez', 'operador.centra.sur@transmetro.gt', '$2y$12$67d9LEWnOnWRiG3WybSXL.LTxIlda590Byg32oAX/41YUNg9Qs7xW', 'operador', 1, NOW(), NOW()),
  (3, 2, 'Luis Sandoval Marroquín', 'operador.monte.maria@transmetro.gt', '$2y$12$67d9LEWnOnWRiG3WybSXL.LTxIlda590Byg32oAX/41YUNg9Qs7xW', 'operador', 1, NOW(), NOW()),
  (4, 3, 'Oscar Morales Gómez', 'operador.javier@transmetro.gt', '$2y$12$67d9LEWnOnWRiG3WybSXL.LTxIlda590Byg32oAX/41YUNg9Qs7xW', 'operador', 1, NOW(), NOW()),
  (5, 4, 'Hugo Méndez Juárez', 'operador.las.charcas@transmetro.gt', '$2y$12$67d9LEWnOnWRiG3WybSXL.LTxIlda590Byg32oAX/41YUNg9Qs7xW', 'operador', 1, NOW(), NOW()),
  (6, 5, 'Claudia Monzón Ramírez', 'operador.el.carmen@transmetro.gt', '$2y$12$67d9LEWnOnWRiG3WybSXL.LTxIlda590Byg32oAX/41YUNg9Qs7xW', 'operador', 1, NOW(), NOW()),
  (7, 6, 'Silvia Reyes Velásquez', 'operador.reformita@transmetro.gt', '$2y$12$67d9LEWnOnWRiG3WybSXL.LTxIlda590Byg32oAX/41YUNg9Qs7xW', 'operador', 1, NOW(), NOW()),
  (8, 7, 'Edgar Pérez Hernández', 'operador.mariscal@transmetro.gt', '$2y$12$67d9LEWnOnWRiG3WybSXL.LTxIlda590Byg32oAX/41YUNg9Qs7xW', 'operador', 1, NOW(), NOW()),
  (9, 8, 'Walter Ramírez Monzón', 'operador.trebol@transmetro.gt', '$2y$12$67d9LEWnOnWRiG3WybSXL.LTxIlda590Byg32oAX/41YUNg9Qs7xW', 'operador', 1, NOW(), NOW()),
  (10, 9, 'María Cifuentes López', 'operador.santa.cecilia@transmetro.gt', '$2y$12$67d9LEWnOnWRiG3WybSXL.LTxIlda590Byg32oAX/41YUNg9Qs7xW', 'operador', 1, NOW(), NOW()),
  (11, 10, 'Wendy Aguilar Chávez', 'operador.bolivar@transmetro.gt', '$2y$12$67d9LEWnOnWRiG3WybSXL.LTxIlda590Byg32oAX/41YUNg9Qs7xW', 'operador', 1, NOW(), NOW()),
  (12, 11, 'Juan Marroquín Sandoval', 'operador.don.bosco@transmetro.gt', '$2y$12$67d9LEWnOnWRiG3WybSXL.LTxIlda590Byg32oAX/41YUNg9Qs7xW', 'operador', 1, NOW(), NOW()),
  (13, 12, 'Sergio López Cifuentes', 'operador.plaza.municipal@transmetro.gt', '$2y$12$67d9LEWnOnWRiG3WybSXL.LTxIlda590Byg32oAX/41YUNg9Qs7xW', 'operador', 1, NOW(), NOW()),
  (14, 13, 'Otto Castillo Godínez', 'operador.plaza.barrios@transmetro.gt', '$2y$12$67d9LEWnOnWRiG3WybSXL.LTxIlda590Byg32oAX/41YUNg9Qs7xW', 'operador', 1, NOW(), NOW()),
  (15, 14, 'Lucía Barrios Ruiz', 'operador.plaza.el.amate@transmetro.gt', '$2y$12$67d9LEWnOnWRiG3WybSXL.LTxIlda590Byg32oAX/41YUNg9Qs7xW', 'operador', 1, NOW(), NOW()),
  (16, 15, 'José Velásquez Reyes', 'operador.gomez.carrillo@transmetro.gt', '$2y$12$67d9LEWnOnWRiG3WybSXL.LTxIlda590Byg32oAX/41YUNg9Qs7xW', 'operador', 1, NOW(), NOW()),
  (17, 16, 'Marvin Godínez Castillo', 'operador.san.agustin@transmetro.gt', '$2y$12$67d9LEWnOnWRiG3WybSXL.LTxIlda590Byg32oAX/41YUNg9Qs7xW', 'operador', 1, NOW(), NOW()),
  (18, 17, 'Estuardo García Orozco', 'operador.parque.centenario@transmetro.gt', '$2y$12$67d9LEWnOnWRiG3WybSXL.LTxIlda590Byg32oAX/41YUNg9Qs7xW', 'operador', 1, NOW(), NOW()),
  (19, 18, 'Sandra Gómez Morales', 'operador.san.sebastian@transmetro.gt', '$2y$12$67d9LEWnOnWRiG3WybSXL.LTxIlda590Byg32oAX/41YUNg9Qs7xW', 'operador', 1, NOW(), NOW()),
  (20, 19, 'Carlos Chávez Aguilar', 'operador.mercado.central@transmetro.gt', '$2y$12$67d9LEWnOnWRiG3WybSXL.LTxIlda590Byg32oAX/41YUNg9Qs7xW', 'operador', 1, NOW(), NOW()),
  (21, 20, 'Byron Orozco García', 'operador.correos@transmetro.gt', '$2y$12$67d9LEWnOnWRiG3WybSXL.LTxIlda590Byg32oAX/41YUNg9Qs7xW', 'operador', 1, NOW(), NOW()),
  (22, 21, 'Rolando Cabrera Estrada', 'operador.beatas.de.belen@transmetro.gt', '$2y$12$67d9LEWnOnWRiG3WybSXL.LTxIlda590Byg32oAX/41YUNg9Qs7xW', 'operador', 1, NOW(), NOW()),
  (23, 22, 'Ana Hernández Pérez', 'operador.paseo.de.las.letras@transmetro.gt', '$2y$12$67d9LEWnOnWRiG3WybSXL.LTxIlda590Byg32oAX/41YUNg9Qs7xW', 'operador', 1, NOW(), NOW()),
  (24, 23, 'Ingrid Ruiz Barrios', 'operador.centro.civico@transmetro.gt', '$2y$12$67d9LEWnOnWRiG3WybSXL.LTxIlda590Byg32oAX/41YUNg9Qs7xW', 'operador', 1, NOW(), NOW()),
  (25, 24, 'Pedro Estrada Cabrera', 'operador.sur.2@transmetro.gt', '$2y$12$67d9LEWnOnWRiG3WybSXL.LTxIlda590Byg32oAX/41YUNg9Qs7xW', 'operador', 1, NOW(), NOW()),
  (26, 25, 'Erick Juárez Méndez', 'operador.simeon.canas@transmetro.gt', '$2y$12$67d9LEWnOnWRiG3WybSXL.LTxIlda590Byg32oAX/41YUNg9Qs7xW', 'operador', 1, NOW(), NOW()),
  (27, 26, 'Rony Sandoval Marroquín', 'operador.hipodromo.del.norte@transmetro.gt', '$2y$12$67d9LEWnOnWRiG3WybSXL.LTxIlda590Byg32oAX/41YUNg9Qs7xW', 'operador', 1, NOW(), NOW()),
  (28, 27, 'Gloria Morales Gómez', 'operador.san.jose.de.la.montana@transmetro.gt', '$2y$12$67d9LEWnOnWRiG3WybSXL.LTxIlda590Byg32oAX/41YUNg9Qs7xW', 'operador', 1, NOW(), NOW()),
  (29, 28, 'Mario Méndez Juárez', 'operador.jocotenango@transmetro.gt', '$2y$12$67d9LEWnOnWRiG3WybSXL.LTxIlda590Byg32oAX/41YUNg9Qs7xW', 'operador', 1, NOW(), NOW());

-- ---------------------------------------------------------------------
-- Datos 8. Parqueos
-- ---------------------------------------------------------------------
INSERT INTO parqueos (id, nombre, direccion, capacidad, created_at, updated_at) VALUES
  (1, 'Predio Centra Sur', 'Zona 12, Villa Nueva', 20, NOW(), NOW()),
  (2, 'Predio Centro Histórico', 'Zona 1, Ciudad de Guatemala', 15, NOW(), NOW()),
  (3, 'Predio Hipódromo', 'Zona 2, Ciudad de Guatemala', 10, NOW(), NOW());

-- ---------------------------------------------------------------------
-- Datos 9. Buses: L12 = 16 (min 14, max 28), L1 = 10 (min 10, max 20), L2 = 5 (min 5, max 10), 4 sin línea
-- ---------------------------------------------------------------------
INSERT INTO buses (id, linea_id, parqueo_id, placa, codigo, capacidad_maxima, estado, created_at, updated_at) VALUES
  (1, 1, 1, 'TM-101', 'BUS-101', 100, 'disponible', NOW(), NOW()),
  (2, 1, 1, 'TM-102', 'BUS-102', 100, 'disponible', NOW(), NOW()),
  (3, 1, 1, 'TM-103', 'BUS-103', 100, 'disponible', NOW(), NOW()),
  (4, 1, 1, 'TM-104', 'BUS-104', 100, 'disponible', NOW(), NOW()),
  (5, 1, 1, 'TM-105', 'BUS-105', 100, 'disponible', NOW(), NOW()),
  (6, 1, 1, 'TM-106', 'BUS-106', 100, 'disponible', NOW(), NOW()),
  (7, 1, 1, 'TM-107', 'BUS-107', 100, 'disponible', NOW(), NOW()),
  (8, 1, 1, 'TM-108', 'BUS-108', 100, 'disponible', NOW(), NOW()),
  (9, 1, 1, 'TM-109', 'BUS-109', 100, 'disponible', NOW(), NOW()),
  (10, 1, 1, 'TM-110', 'BUS-110', 100, 'disponible', NOW(), NOW()),
  (11, 1, 1, 'TM-111', 'BUS-111', 100, 'disponible', NOW(), NOW()),
  (12, 1, 1, 'TM-112', 'BUS-112', 100, 'disponible', NOW(), NOW()),
  (13, 1, 1, 'TM-113', 'BUS-113', 100, 'disponible', NOW(), NOW()),
  (14, 1, 1, 'TM-114', 'BUS-114', 100, 'disponible', NOW(), NOW()),
  (15, 1, 1, 'TM-115', 'BUS-115', 100, 'disponible', NOW(), NOW()),
  (16, 1, 1, 'TM-116', 'BUS-116', 100, 'disponible', NOW(), NOW()),
  (17, 2, 2, 'TM-117', 'BUS-117', 80, 'disponible', NOW(), NOW()),
  (18, 2, 2, 'TM-118', 'BUS-118', 80, 'disponible', NOW(), NOW()),
  (19, 2, 2, 'TM-119', 'BUS-119', 80, 'disponible', NOW(), NOW()),
  (20, 2, 2, 'TM-120', 'BUS-120', 80, 'disponible', NOW(), NOW()),
  (21, 2, 2, 'TM-121', 'BUS-121', 80, 'disponible', NOW(), NOW()),
  (22, 2, 2, 'TM-122', 'BUS-122', 80, 'disponible', NOW(), NOW()),
  (23, 2, 2, 'TM-123', 'BUS-123', 80, 'disponible', NOW(), NOW()),
  (24, 2, 2, 'TM-124', 'BUS-124', 80, 'disponible', NOW(), NOW()),
  (25, 2, 2, 'TM-125', 'BUS-125', 80, 'disponible', NOW(), NOW()),
  (26, 2, 2, 'TM-126', 'BUS-126', 80, 'disponible', NOW(), NOW()),
  (27, 3, 3, 'TM-127', 'BUS-127', 80, 'disponible', NOW(), NOW()),
  (28, 3, 3, 'TM-128', 'BUS-128', 80, 'disponible', NOW(), NOW()),
  (29, 3, 3, 'TM-129', 'BUS-129', 80, 'disponible', NOW(), NOW()),
  (30, 3, 3, 'TM-130', 'BUS-130', 80, 'disponible', NOW(), NOW()),
  (31, 3, 3, 'TM-131', 'BUS-131', 80, 'disponible', NOW(), NOW()),
  (32, NULL, 1, 'TM-132', 'BUS-132', 100, 'disponible', NOW(), NOW()),
  (33, NULL, 1, 'TM-133', 'BUS-133', 100, 'disponible', NOW(), NOW()),
  (34, NULL, 2, 'TM-134', 'BUS-134', 80, 'disponible', NOW(), NOW()),
  (35, NULL, 2, 'TM-135', 'BUS-135', 80, 'disponible', NOW(), NOW());

-- ---------------------------------------------------------------------
-- Datos 10. Pilotos e historial educativo
-- ---------------------------------------------------------------------
INSERT INTO pilotos (id, nombre, dpi, licencia, residencia, telefono, email, estado, created_at, updated_at) VALUES
  (1, 'Julio Monzón Ramírez', '2000340530101', 'A-250137', 'Zona 7, Guatemala', '55179611', 'piloto01@transmetro.gt', 'activo', NOW(), NOW()),
  (2, 'Nery Reyes Velásquez', '2000348440101', 'A-250274', 'Zona 18, Guatemala', '55183788', 'piloto02@transmetro.gt', 'activo', NOW(), NOW()),
  (3, 'Karla Pérez Hernández', '2000356360101', 'A-250411', 'Zona 6, Guatemala', '55187965', 'piloto03@transmetro.gt', 'activo', NOW(), NOW()),
  (4, 'Luis Ramírez Monzón', '2000364280101', 'A-250548', 'Zona 21, Guatemala', '55192142', 'piloto04@transmetro.gt', 'activo', NOW(), NOW()),
  (5, 'Oscar Cifuentes López', '2000372200101', 'A-250685', 'Villa Nueva, Guatemala', '55196319', 'piloto05@transmetro.gt', 'activo', NOW(), NOW()),
  (6, 'Hugo Aguilar Chávez', '2000380120101', 'A-250822', 'Mixco, Guatemala', '55200496', 'piloto06@transmetro.gt', 'activo', NOW(), NOW()),
  (7, 'Claudia Marroquín Sandoval', '2000388040101', 'A-250959', 'Zona 5, Guatemala', '55204673', 'piloto07@transmetro.gt', 'activo', NOW(), NOW()),
  (8, 'Silvia López Cifuentes', '2000395960101', 'A-251096', 'Zona 12, Guatemala', '55208850', 'piloto08@transmetro.gt', 'activo', NOW(), NOW()),
  (9, 'Edgar Castillo Godínez', '2000403880101', 'A-251233', 'Zona 7, Guatemala', '55213027', 'piloto09@transmetro.gt', 'activo', NOW(), NOW()),
  (10, 'Walter Barrios Ruiz', '2000411800101', 'A-251370', 'Zona 18, Guatemala', '55217204', 'piloto10@transmetro.gt', 'activo', NOW(), NOW()),
  (11, 'María Velásquez Reyes', '2000419720101', 'A-251507', 'Zona 6, Guatemala', '55221381', 'piloto11@transmetro.gt', 'activo', NOW(), NOW()),
  (12, 'Wendy Godínez Castillo', '2000427640101', 'A-251644', 'Zona 21, Guatemala', '55225558', 'piloto12@transmetro.gt', 'activo', NOW(), NOW());

INSERT INTO historial_educativo (piloto_id, nivel, institucion, titulo, anio, created_at, updated_at) VALUES
  (1, 'primaria', 'Escuela Oficial Urbana Mixta', 'Sexto Primaria', 1996, NOW(), NOW()),
  (1, 'diversificado', 'Instituto Técnico Vocacional Dr. Imrich Fischmann', 'Perito en Mecánica Automotriz', 2002, NOW(), NOW()),
  (2, 'primaria', 'Escuela Oficial Urbana Mixta', 'Sexto Primaria', 1997, NOW(), NOW()),
  (2, 'basico', 'Instituto Nacional de Educación Básica', 'Tercero Básico', 2003, NOW(), NOW()),
  (3, 'primaria', 'Escuela Oficial Urbana Mixta', 'Sexto Primaria', 1998, NOW(), NOW()),
  (3, 'universitario', 'Universidad de San Carlos de Guatemala', 'Técnico en Transporte', 2004, NOW(), NOW()),
  (4, 'primaria', 'Escuela Oficial Urbana Mixta', 'Sexto Primaria', 1999, NOW(), NOW()),
  (4, 'diversificado', 'Instituto Nacional Central para Varones', 'Bachiller en Ciencias y Letras', 2005, NOW(), NOW()),
  (5, 'primaria', 'Escuela Oficial Urbana Mixta', 'Sexto Primaria', 2000, NOW(), NOW()),
  (5, 'diversificado', 'Instituto Técnico Vocacional Dr. Imrich Fischmann', 'Perito en Mecánica Automotriz', 2006, NOW(), NOW()),
  (6, 'primaria', 'Escuela Oficial Urbana Mixta', 'Sexto Primaria', 2001, NOW(), NOW()),
  (6, 'basico', 'Instituto Nacional de Educación Básica', 'Tercero Básico', 2007, NOW(), NOW()),
  (7, 'primaria', 'Escuela Oficial Urbana Mixta', 'Sexto Primaria', 2002, NOW(), NOW()),
  (7, 'universitario', 'Universidad de San Carlos de Guatemala', 'Técnico en Transporte', 2008, NOW(), NOW()),
  (8, 'primaria', 'Escuela Oficial Urbana Mixta', 'Sexto Primaria', 1995, NOW(), NOW()),
  (8, 'diversificado', 'Instituto Nacional Central para Varones', 'Bachiller en Ciencias y Letras', 2001, NOW(), NOW()),
  (9, 'primaria', 'Escuela Oficial Urbana Mixta', 'Sexto Primaria', 1996, NOW(), NOW()),
  (9, 'diversificado', 'Instituto Técnico Vocacional Dr. Imrich Fischmann', 'Perito en Mecánica Automotriz', 2002, NOW(), NOW()),
  (10, 'primaria', 'Escuela Oficial Urbana Mixta', 'Sexto Primaria', 1997, NOW(), NOW()),
  (10, 'basico', 'Instituto Nacional de Educación Básica', 'Tercero Básico', 2003, NOW(), NOW()),
  (11, 'primaria', 'Escuela Oficial Urbana Mixta', 'Sexto Primaria', 1998, NOW(), NOW()),
  (11, 'universitario', 'Universidad de San Carlos de Guatemala', 'Técnico en Transporte', 2004, NOW(), NOW()),
  (12, 'primaria', 'Escuela Oficial Urbana Mixta', 'Sexto Primaria', 1999, NOW(), NOW()),
  (12, 'diversificado', 'Instituto Nacional Central para Varones', 'Bachiller en Ciencias y Letras', 2005, NOW(), NOW());

-- ---------------------------------------------------------------------
-- Datos 11. Recorridos y estaciones visitadas
-- ---------------------------------------------------------------------
INSERT INTO recorridos (id, linea_id, bus_id, piloto_id, inicio, fin, estado, created_at, updated_at) VALUES
  (1, 1, 1,  1, '2026-10-01 06:00:00', '2026-10-01 06:55:00', 'finalizado', NOW(), NOW()),
  (2, 1, 2,  2, '2026-10-02 07:00:00', NULL, 'en_curso', NOW(), NOW()),
  (3, 2, 17, 3, '2026-10-02 07:10:00', NULL, 'en_curso', NOW(), NOW());
UPDATE buses SET estado = 'en_ruta' WHERE id IN (2, 17);

INSERT INTO recorrido_estaciones (recorrido_id, estacion_id, hora_llegada, hora_salida, pasajeros_a_bordo, espera_extra_min, created_at, updated_at) VALUES
  (1, 1, '2026-10-01 06:00:00', '2026-10-01 06:01:00', 40, 0, NOW(), NOW()),
  (1, 2, '2026-10-01 06:04:00', '2026-10-01 06:05:00', 62, 0, NOW(), NOW()),
  (1, 3, '2026-10-01 06:08:00', '2026-10-01 06:09:00', 78, 0, NOW(), NOW()),
  (1, 4, '2026-10-01 06:12:00', '2026-10-01 06:13:00', 85, 0, NOW(), NOW()),
  (1, 5, '2026-10-01 06:16:00', '2026-10-01 06:17:00', 90, 0, NOW(), NOW()),
  (1, 6, '2026-10-01 06:20:00', '2026-10-01 06:21:00', 96, 0, NOW(), NOW()),
  (1, 7, '2026-10-01 06:24:00', '2026-10-01 06:25:00', 88, 0, NOW(), NOW()),
  (1, 8, '2026-10-01 06:28:00', '2026-10-01 06:29:00', 70, 0, NOW(), NOW()),
  (1, 9, '2026-10-01 06:32:00', '2026-10-01 06:33:00', 64, 0, NOW(), NOW()),
  (1, 10, '2026-10-01 06:36:00', '2026-10-01 06:37:00', 55, 0, NOW(), NOW()),
  (1, 11, '2026-10-01 06:40:00', '2026-10-01 06:41:00', 43, 0, NOW(), NOW()),
  (1, 12, '2026-10-01 06:44:00', '2026-10-01 06:45:00', 30, 0, NOW(), NOW()),
  (1, 13, '2026-10-01 06:48:00', '2026-10-01 06:49:00', 18, 0, NOW(), NOW()),
  (1, 14, '2026-10-01 06:52:00', '2026-10-01 06:53:00', 0, 0, NOW(), NOW()),
  (2, 1, '2026-10-02 07:00:00', '2026-10-02 07:06:00', 20, 5, NOW(), NOW()),
  (2, 2, '2026-10-02 07:10:00', '2026-10-02 07:11:00', 48, 0, NOW(), NOW()),
  (2, 3, '2026-10-02 07:15:00', NULL, 67, 0, NOW(), NOW()),
  (3, 15, '2026-10-02 07:10:00', '2026-10-02 07:11:00', 35, 0, NOW(), NOW()),
  (3, 16, '2026-10-02 07:14:00', NULL, 52, 0, NOW(), NOW());

-- ---------------------------------------------------------------------
-- Datos 12. Pasajeros en espera por estación
-- ---------------------------------------------------------------------
INSERT INTO registros_pasajeros (estacion_id, user_id, cantidad, registrado_en, created_at, updated_at) VALUES
  (1, 2, 60,  '2026-10-02 06:55:00', NOW(), NOW()),
  (8, 9, 155, '2026-10-02 07:05:00', NOW(), NOW()),
  (13, 14, 45,  '2026-10-02 07:08:00', NOW(), NOW()),
  (18, 19, 30,  '2026-10-02 07:12:00', NOW(), NOW());

-- ---------------------------------------------------------------------
-- Datos 13. Alertas (RN-15: 155 en espera >= 150 % de 100; RN-16: 20 de 100 < 25 %)
-- ---------------------------------------------------------------------
INSERT INTO alertas (estacion_id, bus_id, linea_id, tipo, mensaje, estado, atendida_en, created_at, updated_at) VALUES
  (8, NULL, 1, 'alta_demanda', 'Estación Trébol con 155 pasajeros en espera: enviar otro bus.', 'activa', NULL, NOW(), NOW()),
  (1, 2, 1, 'baja_ocupacion', 'BUS-102 con 20 de 100 pasajeros: esperar 5 minutos adicionales.', 'atendida', '2026-10-02 07:06:00', NOW(), NOW());

-- ---------------------------------------------------------------------
-- Datos 14. Pasajeros y Tarjetas Transmetro (saldo inicial 0.00)
-- ---------------------------------------------------------------------
INSERT INTO pasajeros (id, nombre, dpi, telefono, created_at, updated_at) VALUES
  (1, 'Juan García Orozco', '2000435560101', '55229735', NOW(), NOW()),
  (2, 'Sergio Gómez Morales', '2000443480101', '55233912', NOW(), NOW()),
  (3, 'Otto Chávez Aguilar', '2000451400101', '55238089', NOW(), NOW()),
  (4, 'Lucía Orozco García', '2000459320101', '55242266', NOW(), NOW()),
  (5, 'José Cabrera Estrada', '2000467230101', '55246443', NOW(), NOW()),
  (6, 'Marvin Hernández Pérez', '2000475150101', '55250620', NOW(), NOW());

INSERT INTO tarjetas (id, pasajero_id, codigo, saldo, estado, fecha_emision, created_at, updated_at) VALUES
  (1, 1, 'TM-000001', 0.00, 'activa', '2026-04-15', NOW(), NOW()),
  (2, 2, 'TM-000002', 0.00, 'activa', '2026-05-15', NOW(), NOW()),
  (3, 3, 'TM-000003', 0.00, 'activa', '2026-06-15', NOW(), NOW()),
  (4, 4, 'TM-000004', 0.00, 'activa', '2026-07-15', NOW(), NOW()),
  (5, 5, 'TM-000005', 0.00, 'activa', '2026-08-15', NOW(), NOW()),
  (6, 6, 'TM-000006', 0.00, 'activa', '2026-09-15', NOW(), NOW());

-- ---------------------------------------------------------------------
-- Datos 15. Recargas y pagos (el saldo lo actualizan los triggers; tarifa Q1.00)
-- ---------------------------------------------------------------------
INSERT INTO transacciones (tarjeta_id, acceso_id, user_id, tipo, monto) VALUES
  (1, NULL, 2, 'recarga', 20.00),
  (2, NULL, 2, 'recarga', 10.00),
  (3, NULL, 9, 'recarga', 25.00),
  (4, NULL, 9, 'recarga', 5.00),
  (5, NULL, 2, 'recarga', 15.00),
  (6, NULL, 9, 'recarga', 10.00);

INSERT INTO transacciones (tarjeta_id, acceso_id, user_id, tipo, monto) VALUES
  (1, 1, NULL, 'pago', 1.00),
  (1, 25, NULL, 'pago', 1.00),
  (2, 15, NULL, 'pago', 1.00),
  (3, 1, NULL, 'pago', 1.00),
  (3, 32, NULL, 'pago', 1.00),
  (4, 25, NULL, 'pago', 1.00),
  (5, 15, NULL, 'pago', 1.00);

-- Tarjetas no habilitadas y un intento rechazado registrado en el historial
UPDATE tarjetas SET estado = 'bloqueada'  WHERE id = 5;
UPDATE tarjetas SET estado = 'suspendida' WHERE id = 6;
INSERT INTO transacciones (tarjeta_id, acceso_id, user_id, tipo, monto, estado, motivo_rechazo) VALUES
  (5, 15, NULL, 'pago', 1.00, 'rechazada', 'Tarjeta bloqueada');

-- ---------------------------------------------------------------------
-- Datos 16. Bitácora
-- ---------------------------------------------------------------------
INSERT INTO bitacora (user_id, accion, tabla, registro_id, detalle) VALUES
  (1, 'crear', 'lineas', 1, 'Registro de la Línea 12 - Eje Sur'),
  (1, 'asignar_linea', 'buses', 1, 'BUS-101 asignado a la línea L12'),
  (1, 'bloquear', 'tarjetas', 5, 'Tarjeta TM-000005 bloqueada');

-- ---------------------------------------------------------------------
-- Datos 17. Verificación
-- ---------------------------------------------------------------------
SELECT * FROM vw_resumen_lineas;
SELECT codigo, saldo, estado FROM tarjetas;
SELECT 'municipalidades' AS tabla, COUNT(*) AS filas FROM municipalidades
UNION ALL SELECT 'lineas', COUNT(*) FROM lineas
UNION ALL SELECT 'estaciones', COUNT(*) FROM estaciones
UNION ALL SELECT 'linea_estacion', COUNT(*) FROM linea_estacion
UNION ALL SELECT 'accesos', COUNT(*) FROM accesos
UNION ALL SELECT 'guardias', COUNT(*) FROM guardias
UNION ALL SELECT 'users', COUNT(*) FROM users
UNION ALL SELECT 'parqueos', COUNT(*) FROM parqueos
UNION ALL SELECT 'buses', COUNT(*) FROM buses
UNION ALL SELECT 'pilotos', COUNT(*) FROM pilotos
UNION ALL SELECT 'historial_educativo', COUNT(*) FROM historial_educativo
UNION ALL SELECT 'recorridos', COUNT(*) FROM recorridos
UNION ALL SELECT 'recorrido_estaciones', COUNT(*) FROM recorrido_estaciones
UNION ALL SELECT 'registros_pasajeros', COUNT(*) FROM registros_pasajeros
UNION ALL SELECT 'alertas', COUNT(*) FROM alertas
UNION ALL SELECT 'pasajeros', COUNT(*) FROM pasajeros
UNION ALL SELECT 'tarjetas', COUNT(*) FROM tarjetas
UNION ALL SELECT 'transacciones', COUNT(*) FROM transacciones
UNION ALL SELECT 'bitacora', COUNT(*) FROM bitacora;
