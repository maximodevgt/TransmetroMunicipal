-- =====================================================================
-- 05. Pagos simulados de la Tarjeta Transmetro
-- Agrega a transacciones los datos de recargas en efectivo y en línea
-- (pasarela SIMULADA) sin tocar los triggers: el saldo lo siguen
-- calculando trg_transacciones_validar y trg_transacciones_saldo.
--
-- Nunca se guarda el número completo de una tarjeta bancaria ni el CVV:
-- solo la marca, los últimos 4 dígitos y una referencia inventada.
--
-- Se puede ejecutar varias veces sin error.
-- =====================================================================
USE transmetro;

-- Procedimientos auxiliares (se borran al final): agregan una columna,
-- un índice único, una llave foránea o una restricción CHECK solo si no existe.
DROP PROCEDURE IF EXISTS tm_agregar_columna;
DROP PROCEDURE IF EXISTS tm_agregar_restriccion;

DELIMITER $$

CREATE PROCEDURE tm_agregar_columna(IN p_tabla VARCHAR(64), IN p_columna VARCHAR(64), IN p_definicion TEXT)
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_tabla AND COLUMN_NAME = p_columna
  ) THEN
    SET @sql := CONCAT('ALTER TABLE ', p_tabla, ' ADD COLUMN ', p_columna, ' ', p_definicion);
    PREPARE sentencia FROM @sql;
    EXECUTE sentencia;
    DEALLOCATE PREPARE sentencia;
  END IF;
END$$

CREATE PROCEDURE tm_agregar_restriccion(IN p_tabla VARCHAR(64), IN p_nombre VARCHAR(64), IN p_definicion TEXT)
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_tabla AND CONSTRAINT_NAME = p_nombre
  ) THEN
    SET @sql := CONCAT('ALTER TABLE ', p_tabla, ' ADD CONSTRAINT ', p_nombre, ' ', p_definicion);
    PREPARE sentencia FROM @sql;
    EXECUTE sentencia;
    DEALLOCATE PREPARE sentencia;
  END IF;
END$$

DELIMITER ;

-- 1. Columnas nuevas de transacciones
-- metodo: cómo se pagó una recarga (en ventanilla o con la pasarela simulada). NULL en pasajes.
CALL tm_agregar_columna('transacciones', 'metodo', "ENUM('efectivo','tarjeta') NULL AFTER tipo");
-- estacion_id: estación de la ventanilla (recarga en efectivo) o del torniquete (pasaje), para el comprobante.
CALL tm_agregar_columna('transacciones', 'estacion_id', 'BIGINT UNSIGNED NULL AFTER acceso_id');
-- Datos permitidos de la tarjeta bancaria simulada: marca y últimos 4 dígitos. Nada más.
CALL tm_agregar_columna('transacciones', 'tarjeta_marca', 'VARCHAR(20) NULL AFTER motivo_rechazo');
CALL tm_agregar_columna('transacciones', 'tarjeta_ultimos4', 'CHAR(4) NULL AFTER tarjeta_marca');
-- Referencia inventada por la pasarela simulada (por ejemplo SIM-7F3A9C21).
CALL tm_agregar_columna('transacciones', 'referencia', 'VARCHAR(40) NULL AFTER tarjeta_ultimos4');
-- Llave de idempotencia por intento de pago: evita el doble cobro si se envía dos veces.
CALL tm_agregar_columna('transacciones', 'llave_idempotencia', 'VARCHAR(64) NULL AFTER referencia');

-- 2. Restricciones
CALL tm_agregar_restriccion('transacciones', 'uk_transacciones_llave', 'UNIQUE (llave_idempotencia)');
CALL tm_agregar_restriccion('transacciones', 'fk_transacciones_estacion',
  'FOREIGN KEY (estacion_id) REFERENCES estaciones(id) ON UPDATE CASCADE ON DELETE SET NULL');
-- Solo 4 dígitos: aunque hubiera un error en el código, la base no acepta un número completo.
CALL tm_agregar_restriccion('transacciones', 'chk_transacciones_ultimos4',
  "CHECK (tarjeta_ultimos4 IS NULL OR tarjeta_ultimos4 REGEXP '^[0-9]{4}$')");

DROP PROCEDURE IF EXISTS tm_agregar_columna;
DROP PROCEDURE IF EXISTS tm_agregar_restriccion;

-- 3. Verificación: deben aparecer las 6 columnas nuevas.
SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE
  FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'transacciones'
   AND COLUMN_NAME IN ('metodo', 'estacion_id', 'tarjeta_marca', 'tarjeta_ultimos4', 'referencia', 'llave_idempotencia')
 ORDER BY ORDINAL_POSITION;
