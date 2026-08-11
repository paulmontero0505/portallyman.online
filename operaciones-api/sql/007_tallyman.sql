-- ============================================================
-- Operaciones · Registro de Actividad Tallyman · Migración 007
-- Ejecutar sobre la BD `operaciones` (ya existente).
-- Idempotente: CREATE TABLE IF NOT EXISTS + INSERT ... ON DUPLICATE KEY.
-- ============================================================
USE operaciones;

-- ---------- Catálogo de actividades (21 del formulario HANDOVER) ----------
CREATE TABLE IF NOT EXISTS tallyman_actividades (
  id     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(80)  NOT NULL,
  activo TINYINT(1)   NOT NULL DEFAULT 1,
  orden  INT          NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_tallyman_act_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO tallyman_actividades (nombre, orden) VALUES
  ('Containers Loading/Discharge', 1),
  ('Corn Loading/Discharge', 2),
  ('Salt Loading/Discharge', 3),
  ('Soybean Unloading/Loading', 4),
  ('Bulk Carrier Loading/Discharge', 5),
  ('Big Bags Loading/Discharge', 6),
  ('General Cargo Loading/Discharge', 7),
  ('Car Loading/Discharge', 8),
  ('Minerals', 9),
  ('Fishmeals', 10),
  ('Container deconsolidation', 11),
  ('Car deconsolidation', 12),
  ('Containers Dispatch', 13),
  ('Corn Dispatch', 14),
  ('Salt Dispatch', 15),
  ('Soybean Dispatch', 16),
  ('Bulk Carrier Dispatch', 17),
  ('Big Bags Dispatch', 18),
  ('General Cargo Dispatch', 19),
  ('Car Dispatch', 20),
  ('Reception of Salt', 21)
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre);

-- ---------- Registros de actividad por turno ----------
CREATE TABLE IF NOT EXISTS tallyman_registros (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  fecha_turno    DATE          NOT NULL,
  turno          VARCHAR(20)   NOT NULL,
  ubicacion_tipo ENUM('BERTH','YARD') NOT NULL,
  ubicacion      VARCHAR(40)   NOT NULL,
  nave_id        INT UNSIGNED  NULL,
  actividad_id   INT UNSIGNED  NOT NULL,
  estado_pos     ENUM('ACTIVE','INACTIVE','FINISH') NOT NULL DEFAULT 'ACTIVE',
  status_act     ENUM('Inicio','En Proceso','Culminado') NOT NULL DEFAULT 'Inicio',
  planned        DECIMAL(14,2) NULL,
  executed       DECIMAL(14,2) NOT NULL DEFAULT 0,
  productivity   DECIMAL(12,2) NULL,
  details        TEXT          NULL,
  -- Manifiesto de carga (Patio/Yard) — alimentan el reporte de relevo
  cargo_type     VARCHAR(40)   NULL,
  bl             VARCHAR(120)  NULL,
  producto       VARCHAR(120)  NULL,
  unidades       DECIMAL(14,2) NULL,
  tons           DECIMAL(14,2) NULL,
  ubic_codigo    VARCHAR(40)   NULL,
  coord_entrante VARCHAR(120)  NULL,
  coord_saliente VARCHAR(120)  NULL,
  registrado_por VARCHAR(120)  NOT NULL,
  fecha_registro TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_tr_nave FOREIGN KEY (nave_id) REFERENCES naves(id) ON DELETE SET NULL,
  CONSTRAINT fk_tr_act  FOREIGN KEY (actividad_id) REFERENCES tallyman_actividades(id),
  KEY idx_tr_turno (fecha_turno, turno),
  KEY idx_tr_nave (nave_id),
  KEY idx_tr_acum (nave_id, actividad_id, ubicacion, fecha_turno)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Incidencias del turno ----------
CREATE TABLE IF NOT EXISTS tallyman_incidencias (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  fecha_turno    DATE         NOT NULL,
  turno          VARCHAR(20)  NOT NULL,
  hubo           TINYINT(1)   NOT NULL DEFAULT 0,
  detalle        TEXT         NULL,
  registrado_por VARCHAR(120) NOT NULL,
  fecha_registro TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ti_turno (fecha_turno, turno)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
