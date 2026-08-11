-- ════════════════════════════════════════════════════════════════════
-- ESTIBA_TURNO · 015 · Módulo Reporte de Inspección
-- Checklist de campo (señalización, iluminación, orden y limpieza, EPPs,
-- condiciones inseguras, ergonomía, etc.) asociado a un tally involucrado.
-- Catálogo de criterios fijo en código: includes/reporte_inspeccion_catalogo.php
-- El checklist completo (item + estado + observación) se persiste como JSON
-- en la columna `criterios`.
-- Ejecutar con: mysql -uroot estiba_turno < sql/015_reporte_inspeccion.sql
-- Idempotente (CREATE TABLE IF NOT EXISTS).
-- ════════════════════════════════════════════════════════════════════

USE portally_system;

CREATE TABLE IF NOT EXISTS reporte_inspeccion (
  id                  INT(11)      NOT NULL AUTO_INCREMENT,
  tally_id            INT(11)      NULL,                  -- FK colaboradores; SET NULL si se borra
  tally_nombre        VARCHAR(150) NOT NULL,               -- copia congelada al registrar
  tally_cargo         VARCHAR(60)  NULL,
  zona_trabajo        VARCHAR(80)  NOT NULL,
  area_involucrada    VARCHAR(80)  NOT NULL DEFAULT 'Operaciones',
  fecha               DATE         NOT NULL,
  inspector           VARCHAR(100) NOT NULL,               -- nombre de la sesión que registra
  inspector_id        INT(11)      NULL,
  criterios           TEXT         NOT NULL,               -- JSON: [{item,estado,observaciones}]
  medidas_tomar       TEXT         NULL,
  recomendaciones     TEXT         NULL,
  created_at          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_ri_fecha (fecha),
  KEY ix_ri_tally (tally_id),
  CONSTRAINT fk_ri_tally FOREIGN KEY (tally_id)
     REFERENCES colaboradores(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
