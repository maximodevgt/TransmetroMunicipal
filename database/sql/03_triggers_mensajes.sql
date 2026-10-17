-- =====================================================================
-- 03. Mensajes de los triggers en español con tildes
-- Recrea los 4 triggers de 01_transmetro.sql con la misma lógica; solo
-- cambian los textos de MESSAGE_TEXT. Se puede ejecutar varias veces.
-- Ejecutar con la base transmetro seleccionada.
-- =====================================================================
USE transmetro;

DROP TRIGGER IF EXISTS trg_buses_max_insert;
DROP TRIGGER IF EXISTS trg_buses_max_update;
DROP TRIGGER IF EXISTS trg_transacciones_validar;
DROP TRIGGER IF EXISTS trg_transacciones_saldo;

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

-- Verificación: deben aparecer los 4 triggers.
SELECT TRIGGER_NAME, EVENT_MANIPULATION, EVENT_OBJECT_TABLE
  FROM information_schema.TRIGGERS
 WHERE TRIGGER_SCHEMA = 'transmetro'
 ORDER BY TRIGGER_NAME;
