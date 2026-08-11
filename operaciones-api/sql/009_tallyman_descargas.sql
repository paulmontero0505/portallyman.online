-- ============================================================
-- Operaciones · Migración 009 — Descarga Directa / Interna en tallyman
-- El "executed" del turno se desglosa en dos flujos opcionales:
--   directa  = descarga directa (p. ej. barco → camión)
--   interna  = descarga interna (p. ej. barco → almacén)
-- `executed` sigue siendo el total (directa + interna) y alimenta el
-- acumulado/avance. NOTA: ALTER no idempotente (ejecutar una sola vez).
-- ============================================================
USE operaciones;

ALTER TABLE tallyman_registros
  ADD COLUMN directa DECIMAL(14,2) NULL AFTER executed,
  ADD COLUMN interna DECIMAL(14,2) NULL AFTER directa;
