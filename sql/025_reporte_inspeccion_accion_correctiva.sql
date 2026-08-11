-- ════════════════════════════════════════════════════════════════════
-- ESTIBA_TURNO · 025 · Acción correctiva sobre un reporte de inspección
-- ────────────────────────────────────────────────────────────────────
-- El reporte de inspección es la PETICIÓN. La acción correctiva es la
-- RESPUESTA que registra el coordinador después, desde la columna
-- Acciones del listado. Es UNA por reporte y se puede editar.
--
--   · accion_opciones   → JSON con las claves marcadas del catálogo
--                         (includes/reporte_inspeccion_catalogo.php).
--   · accion_comentario → texto libre del coordinador.
--   · accion_por / _id / _fecha → quién respondió y cuándo.
--   · reporte_inspeccion_evidencias → adjuntos (imagen, PDF, Office,
--     video). Mismo esquema que asistencias_evidencias: se suben a Drive
--     y, si Drive falla, queda el respaldo local marcado 'pendiente'.
--
-- Un reporte sin accion_fecha está PENDIENTE; con fecha, ATENDIDO.
--
-- Idempotente. Se ejecuta sobre la base YA seleccionada (en phpMyAdmin:
-- elige la base antes de importar).
-- ════════════════════════════════════════════════════════════════════

-- ─── Columnas de la respuesta ───────────────────────────────────────
SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reporte_inspeccion'
                AND COLUMN_NAME = 'accion_opciones');
SET @ddl := IF(@col = 0,
  'ALTER TABLE reporte_inspeccion
     ADD COLUMN accion_opciones   TEXT         NULL AFTER recomendaciones,
     ADD COLUMN accion_comentario TEXT         NULL AFTER accion_opciones,
     ADD COLUMN accion_por        VARCHAR(100) NULL AFTER accion_comentario,
     ADD COLUMN accion_por_id     INT(11)      NULL AFTER accion_por,
     ADD COLUMN accion_fecha      DATETIME     NULL AFTER accion_por_id',
  'SELECT "columnas de acción correctiva ya existen" AS info');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Índice para listar rápido pendientes vs atendidos.
SET @ix := (SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reporte_inspeccion'
               AND INDEX_NAME = 'ix_ri_accion');
SET @ddl := IF(@ix = 0,
  'ALTER TABLE reporte_inspeccion ADD KEY ix_ri_accion (accion_fecha)',
  'SELECT "ix_ri_accion ya existe" AS info');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ─── Evidencias de la acción correctiva ─────────────────────────────
CREATE TABLE IF NOT EXISTS reporte_inspeccion_evidencias (
  id             INT(11)      NOT NULL AUTO_INCREMENT,
  reporte_id     INT(11)      NOT NULL,
  nombre_archivo VARCHAR(150) NOT NULL,
  mime           VARCHAR(120) NOT NULL,
  peso_bytes     INT UNSIGNED NOT NULL,
  drive_file_id  VARCHAR(120) NULL,
  drive_url      VARCHAR(255) NULL,
  ruta_local     VARCHAR(255) NULL,                   -- respaldo local si Drive falló
  estado         ENUM('subido','pendiente','error') NOT NULL DEFAULT 'pendiente',
  error_msg      VARCHAR(255) NULL,
  created_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_rie_reporte (reporte_id),
  CONSTRAINT fk_rie_reporte FOREIGN KEY (reporte_id)
     REFERENCES reporte_inspeccion(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Verificación
-- SHOW COLUMNS FROM reporte_inspeccion LIKE 'accion_%';
-- SELECT id, tally_nombre, accion_fecha, accion_por FROM reporte_inspeccion;
