-- ============================================================
-- Operaciones · Migración 008 — Actividad por defecto de la nave
-- Asocia a cada nave una actividad del catálogo tallyman_actividades.
-- Sirve para que, al registrar indicadores en el Tallyman, la actividad
-- se autocomplete según la nave (y su ubicación) que se trabaja.
-- Requiere que la migración 007_tallyman.sql ya se haya ejecutado.
-- NOTA: ALTER no idempotente (ejecutar una sola vez).
-- ============================================================
USE operaciones;

ALTER TABLE naves
  ADD COLUMN actividad_id INT UNSIGNED NULL AFTER tipo_nave_id,
  ADD CONSTRAINT fk_naves_actividad
    FOREIGN KEY (actividad_id) REFERENCES tallyman_actividades(id)
    ON UPDATE CASCADE ON DELETE SET NULL;
