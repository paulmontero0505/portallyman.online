-- ════════════════════════════════════════════════════════════════════
-- ESTIBA_TURNO · 012 · Reactivar DNI en colaboradores
-- ────────────────────────────────────────────────────────────────────
-- El DNI se había eliminado en 003_remove_dni.sql (se usaba solo `codigo`).
-- Se reactiva para el AUTO-REGISTRO público de colaboradores (ingreso/refrigerio):
-- el colaborador se identifica por su DNI.
--   · dni NULL permitido (varios NULL conviven con UNIQUE en MySQL) para no
--     romper las filas existentes; se pobla luego desde el CRUD/importación.
-- Ejecutar en cPanel / phpMyAdmin sobre la base portally_system.
-- ════════════════════════════════════════════════════════════════════

USE portally_system;

ALTER TABLE colaboradores ADD COLUMN dni VARCHAR(8) NULL AFTER codigo;
ALTER TABLE colaboradores ADD UNIQUE KEY uq_dni (dni);

-- Verificación
-- SHOW COLUMNS FROM colaboradores LIKE 'dni';
