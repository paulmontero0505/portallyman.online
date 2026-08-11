-- ════════════════════════════════════════════════════════════════════
-- ESTIBA_TURNO · 004 · Turnos persistidos + catálogos
-- Reemplaza el seed hardcoded de js/data-source.js.
-- Crea:
--   · catálogos editables: jornadas, limites_pausa, ubicaciones, funciones
--   · turnos (fecha + jornada), turno_personal, turno_eventos
-- Ejecutar con: mysql -uroot estiba_turno < sql/004_turnos.sql
-- Idempotente (CREATE TABLE IF NOT EXISTS + INSERT IGNORE).
-- ════════════════════════════════════════════════════════════════════

USE estiba_turno;

-- ─── jornadas ────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS jornadas (
  id           INT(11)     NOT NULL AUTO_INCREMENT,
  codigo       CHAR(1)     NOT NULL,                 -- M / T / N
  nombre       VARCHAR(30) NOT NULL,
  hora_inicio  TIME        NOT NULL,
  hora_fin     TIME        NOT NULL,
  orden        INT(11)     NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_jornada_codigo (codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO jornadas (codigo, nombre, hora_inicio, hora_fin, orden) VALUES
('M', 'Mañana', '06:00:00', '14:00:00', 1),
('T', 'Tarde',  '14:00:00', '22:00:00', 2),
('N', 'Noche',  '22:00:00', '06:00:00', 3);

-- ─── limites_pausa ───────────────────────────────────────────────────
-- limite_min NULL = sin alerta para ese tipo.
CREATE TABLE IF NOT EXISTS limites_pausa (
  tipo        VARCHAR(20) NOT NULL,                  -- refrigerio|permiso|traslado
  limite_min  INT(11)     NULL,
  PRIMARY KEY (tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO limites_pausa (tipo, limite_min) VALUES
('refrigerio', 30),
('permiso',    60),
('traslado',   NULL);

-- ─── ubicaciones ─────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS ubicaciones (
  id      INT(11)     NOT NULL AUTO_INCREMENT,
  nombre  VARCHAR(60) NOT NULL,
  activo  TINYINT(1)  NOT NULL DEFAULT 1,
  orden   INT(11)     NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ubicacion_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO ubicaciones (nombre, orden) VALUES
('Bahía 1', 1), ('Bahía 2', 2), ('Bahía 3', 3), ('Bahía 4', 4),
('Muelle Norte', 5), ('Muelle Sur', 6), ('Patio Maniobras', 7),
('Almacén A', 8), ('Almacén B', 9), ('Caseta Control', 10);

-- ─── funciones ───────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS funciones (
  id      INT(11)     NOT NULL AUTO_INCREMENT,
  nombre  VARCHAR(60) NOT NULL,
  activo  TINYINT(1)  NOT NULL DEFAULT 1,
  orden   INT(11)     NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_funcion_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO funciones (nombre, orden) VALUES
('Winchero', 1), ('Estibador', 2), ('Señalero', 3), ('Tractorista', 4),
('Capataz', 5), ('Apoyo Bodega', 6), ('Apoyo Muelle', 7), ('Lashing', 8);

-- ─── turnos ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS turnos (
  id          INT(11) NOT NULL AUTO_INCREMENT,
  fecha       DATE    NOT NULL,
  jornada_id  INT(11) NOT NULL,
  estado      ENUM('abierto','cerrado') NOT NULL DEFAULT 'abierto',
  abierto_por INT(11) NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_turno_fecha_jornada (fecha, jornada_id),
  KEY ix_turno_jornada (jornada_id),
  CONSTRAINT fk_turno_jornada FOREIGN KEY (jornada_id) REFERENCES jornadas(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── turno_personal ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS turno_personal (
  id             INT(11)     NOT NULL AUTO_INCREMENT,
  turno_id       INT(11)     NOT NULL,
  colaborador_id INT(11)     NOT NULL,
  funcion        VARCHAR(60) NOT NULL,
  ubicacion      VARCHAR(60) NULL,
  estado         ENUM('activo','refrigerio','incidencia') NOT NULL DEFAULT 'activo',
  created_at     TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_turno_colaborador (turno_id, colaborador_id),
  KEY ix_tp_turno (turno_id),
  KEY ix_tp_colaborador (colaborador_id),
  CONSTRAINT fk_tp_turno       FOREIGN KEY (turno_id)       REFERENCES turnos(id)        ON DELETE CASCADE,
  CONSTRAINT fk_tp_colaborador FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── turno_eventos (bitácora) ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS turno_eventos (
  id                INT(11) NOT NULL AUTO_INCREMENT,
  turno_personal_id INT(11) NOT NULL,
  tipo              ENUM('traslado','refrigerio','permiso') NOT NULL,
  hora_inicio       TIME    NOT NULL,
  hora_fin          TIME    NULL,
  observaciones     TEXT    NULL,
  created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_ev_tp (turno_personal_id),
  CONSTRAINT fk_ev_tp FOREIGN KEY (turno_personal_id) REFERENCES turno_personal(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
