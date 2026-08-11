-- ════════════════════════════════════════════════════════════════════
-- ESTIBA_TURNO · 007 · Roles, motivos, auditoría de acciones
--   · usuarios.rol: agrega Supervisor y Coordinador
--   · motivos: catálogo para observaciones de eventos y cambios
--   · turno_eventos.motivo: motivo seleccionado (catálogo)
--   · turno_acciones: bitácora de auditoría (toda acción del turno)
-- Ejecutar con: mysql -uroot estiba_turno < sql/007_roles_motivos_auditoria.sql
-- NOTA: el ALTER de rol y el ADD COLUMN no son idempotentes (ejecutar una vez).
-- ════════════════════════════════════════════════════════════════════

USE estiba_turno;

-- ─── Roles ───────────────────────────────────────────────────────────
ALTER TABLE usuarios
  MODIFY rol ENUM('Administrador','Supervisor','Coordinador','Operador')
  NOT NULL DEFAULT 'Coordinador';

-- ─── Catálogo de motivos ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS motivos (
  id             INT(11)     NOT NULL AUTO_INCREMENT,
  nombre         VARCHAR(60) NOT NULL,
  requiere_texto TINYINT(1)  NOT NULL DEFAULT 0,   -- "Otros" → exige texto manual
  activo         TINYINT(1)  NOT NULL DEFAULT 1,
  orden          INT(11)     NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_motivo (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO motivos (nombre, requiere_texto, orden) VALUES
('Lactancia', 0, 1),
('Tópico', 0, 2),
('Vestuario', 0, 3),
('Permiso Personal', 0, 4),
('Sin Operaciones en Terminal', 0, 5),
('Cita con Labour', 0, 6),
('Otros', 1, 7);

-- ─── Motivo en eventos ───────────────────────────────────────────────
ALTER TABLE turno_eventos ADD COLUMN motivo VARCHAR(60) NULL AFTER tipo;

-- ─── Auditoría de acciones del turno ─────────────────────────────────
CREATE TABLE IF NOT EXISTS turno_acciones (
  id                 INT(11)      NOT NULL AUTO_INCREMENT,
  turno_id           INT(11)      NOT NULL,
  usuario_id         INT(11)      NULL,
  usuario_nombre     VARCHAR(100) NULL,
  usuario_rol        VARCHAR(20)  NULL,
  tipo               VARCHAR(30)  NOT NULL,   -- alta|baja|cambio|estado|evento|evento_borrado|cierre
  colaborador_codigo VARCHAR(20)  NULL,
  colaborador_nombre VARCHAR(150) NULL,
  detalle            TEXT         NULL,
  created_at         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_acc_turno (turno_id),
  CONSTRAINT fk_acc_turno FOREIGN KEY (turno_id) REFERENCES turnos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
