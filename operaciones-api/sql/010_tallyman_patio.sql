-- ============================================================
-- Operaciones · Migración 010 — Nombre libre de nave para Patio
-- `nave_patio` almacena nombres de texto creados en el formulario
-- YARD cuando no corresponden a una nave real del módulo Operaciones.
-- Desaparecen del desplegable cuando su registro llega a Culminado.
-- NOTA: ALTER no idempotente (ejecutar una sola vez).
-- ============================================================
USE operaciones;

ALTER TABLE tallyman_registros
  ADD COLUMN nave_patio VARCHAR(100) NULL AFTER nave_id;
