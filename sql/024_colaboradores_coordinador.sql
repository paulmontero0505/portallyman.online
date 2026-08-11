-- ════════════════════════════════════════════════════════════════════
-- ESTIBA_TURNO · 024 · Coordinador Tallyman a cargo del colaborador
-- ────────────────────────────────────────────────────────────────────
-- Relaciona cada colaborador con el usuario Coordinador responsable.
-- El catálogo de coordinadores NO se duplica: son los registros de
-- `usuarios` con rol='Coordinador' (ver api/get_coordinadores.php).
--
--   · coordinador_id NULL  → «Sin asignar». Necesario para no romper los
--     colaboradores ya cargados ni la importación Excel (que no trae la
--     columna).
--   · ON DELETE SET NULL   → borrar un usuario coordinador deja a sus
--     colaboradores sin asignar, en vez de bloquear el borrado.
--
-- Idempotente: sólo añade columna y FK si aún no existen.
-- Se ejecuta sobre la base YA seleccionada (en phpMyAdmin: elige la base
-- antes de importar). No lleva "USE" para funcionar en local y en el
-- servidor sin importar el nombre de la base.
-- ════════════════════════════════════════════════════════════════════

-- ─── Columna ────────────────────────────────────────────────────────
SET @col := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME   = 'colaboradores'
     AND COLUMN_NAME  = 'coordinador_id'
);
SET @ddl := IF(@col = 0,
  'ALTER TABLE colaboradores ADD COLUMN coordinador_id INT(11) NULL AFTER cuadrilla',
  'SELECT "coordinador_id ya existe" AS info');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ─── Índice (acelera el filtro «colaboradores a cargo de X») ────────
SET @ix := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME   = 'colaboradores'
     AND INDEX_NAME   = 'ix_coordinador'
);
SET @ddl := IF(@ix = 0,
  'ALTER TABLE colaboradores ADD KEY ix_coordinador (coordinador_id)',
  'SELECT "ix_coordinador ya existe" AS info');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ─── Clave foránea hacia usuarios ───────────────────────────────────
SET @fk := (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
   WHERE TABLE_SCHEMA    = DATABASE()
     AND TABLE_NAME      = 'colaboradores'
     AND CONSTRAINT_NAME = 'fk_col_coordinador'
);
SET @ddl := IF(@fk = 0,
  'ALTER TABLE colaboradores
     ADD CONSTRAINT fk_col_coordinador FOREIGN KEY (coordinador_id)
         REFERENCES usuarios(id) ON DELETE SET NULL ON UPDATE CASCADE',
  'SELECT "fk_col_coordinador ya existe" AS info');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verificación
-- SHOW COLUMNS FROM colaboradores LIKE 'coordinador_id';
-- SELECT c.nombre, u.nombre AS coordinador
--   FROM colaboradores c LEFT JOIN usuarios u ON u.id = c.coordinador_id;
