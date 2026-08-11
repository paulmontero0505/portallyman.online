-- ════════════════════════════════════════════════════════════════════
-- ESTIBA_TURNO · 003 · Eliminar DNI · usar Código como identidad
-- ────────────────────────────────────────────────────────────────────
-- Cambios:
--   1. Elimina el índice único uq_dni
--   2. Elimina la columna dni
--   3. Hace codigo NOT NULL (era NULL porque se auto-generaba post-insert;
--      ahora el usuario lo provee siempre).
--
-- Pre-requisito: tabla colaboradores vacía (TRUNCATE previo). El DROP es
-- destructivo: si hay datos con DNI que deban preservarse, exportar antes.
--
-- Ejecutar con: mysql -uroot estiba_turno < sql/003_remove_dni.sql
-- ════════════════════════════════════════════════════════════════════

USE estiba_turno;

ALTER TABLE colaboradores DROP INDEX uq_dni;
ALTER TABLE colaboradores DROP COLUMN dni;
ALTER TABLE colaboradores MODIFY codigo VARCHAR(20) NOT NULL;
