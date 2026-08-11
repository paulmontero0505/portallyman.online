-- ════════════════════════════════════════════════════════════════════
-- ESTIBA_TURNO · 027 · Nave a la que se asigna al colaborador (Fase 2)
-- ────────────────────────────────────────────────────────────────────
-- Hasta ahora NO existía ninguna relación entre un colaborador y una nave:
-- turno_personal guardaba `ubicacion` como texto del catálogo («Muelle 1»,
-- «Gate - CI»…) y nada más. Por eso el submódulo Operaciones → Naves en
-- muelle no puede decir cuánta gente trabajó cada nave.
--
-- Se probaron las tres inferencias posibles y las TRES dan cero contra los
-- datos reales:
--   · turno_personal.ubicacion = naves.muelle  → 0 coincidencias (hay gente
--     en «Muelle 1» y las naves están en Muelle 4, Muelle 2 y una en NULL).
--   · puente vía tallyman_registros            → los vocabularios de
--     ubicación sólo coinciden en «Muelle 1» y las fechas no se solapan.
-- Un número inferido sería peor que ninguno: el día que empiece a dar
-- cifras no habría contra qué contrastarlas. Se captura explícitamente.
--
-- SIN CLAVE FORÁNEA, a propósito: `naves` vive en la base de Operaciones,
-- que es otra base y con una API Node propia que apunta a separarse de
-- servidor. Se valida en aplicación, igual que hace tallyman_registros,
-- que tampoco referencia nada de portally_system.
--
-- Idempotente. Se ejecuta sobre la base YA seleccionada (en phpMyAdmin:
-- elige la base antes de importar). No lleva "USE".
-- ════════════════════════════════════════════════════════════════════

-- ─── Columna nave_id ────────────────────────────────────────────────
-- NULL = sin nave. Es el caso normal: Gate, Balanza, Administrativo y
-- cualquier ubicación que no sea un muelle nunca tendrán nave.
SET @col := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME   = 'turno_personal'
     AND COLUMN_NAME  = 'nave_id'
);
SET @ddl := IF(@col = 0,
  'ALTER TABLE turno_personal ADD COLUMN nave_id INT(11) NULL AFTER ubicacion',
  'SELECT "nave_id ya existe" AS info');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ─── Índice (acelera «cuánta gente trabajó la nave X») ──────────────
SET @ix := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME   = 'turno_personal'
     AND INDEX_NAME   = 'ix_tp_nave'
);
SET @ddl := IF(@ix = 0,
  'ALTER TABLE turno_personal ADD KEY ix_tp_nave (nave_id)',
  'SELECT "ix_tp_nave ya existe" AS info');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ─── BUG: el enum de `estado` no admite dos estados que la UI SÍ ofrece ──
-- El drawer de index.php ofrece «Traslado» y «Permiso», y
-- api/update_asignacion.php los acepta en $estadosOk. Pero la columna es
-- ENUM('activo','refrigerio','incidencia'): con sql_mode no estricto, esos
-- dos valores se guardan como CADENA VACÍA en vez de fallar.
--
-- Queda evidencia en la propia auditoría del sistema:
--     estado:  → Traslado        ← el valor anterior salió vacío
--     estado:  → Refrigerio
--
-- Añadir los valores es no destructivo: los existentes no se tocan.
SET @tipo := (
  SELECT COLUMN_TYPE FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME   = 'turno_personal'
     AND COLUMN_NAME  = 'estado'
);
SET @ddl := IF(@tipo NOT LIKE '%traslado%',
  'ALTER TABLE turno_personal
     MODIFY COLUMN estado ENUM(''activo'',''refrigerio'',''incidencia'',''traslado'',''permiso'')
     NOT NULL DEFAULT ''activo''',
  'SELECT "el enum estado ya admite traslado/permiso" AS info');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ─── Reparación de filas ya corrompidas ─────────────────────────────
-- Las que se guardaron como '' por el bug anterior vuelven a 'activo':
-- es el estado por defecto y el único que no afirma nada que no sepamos.
UPDATE turno_personal SET estado = 'activo' WHERE estado = '';

-- Verificación
-- SHOW COLUMNS FROM turno_personal LIKE 'nave_id';
-- SHOW COLUMNS FROM turno_personal LIKE 'estado';
-- SELECT estado, COUNT(*) FROM turno_personal GROUP BY estado;
