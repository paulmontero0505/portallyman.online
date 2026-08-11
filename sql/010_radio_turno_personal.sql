-- ============================================================
-- ESTIBA_TURNO · turno_personal: indicador de radio asignada
-- El coordinador marca, en el "Cambio de puesto / estado", si el
-- colaborador porta radio durante el turno. Alimenta el indicador
-- "Personal y radios" del relevo de turno.
-- ============================================================
USE estiba_turno;

ALTER TABLE turno_personal
  ADD COLUMN radio TINYINT(1) NOT NULL DEFAULT 0 AFTER estado;
