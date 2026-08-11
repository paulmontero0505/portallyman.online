-- ════════════════════════════════════════════════════════════════════
-- ESTIBA_TURNO · 019 · Módulo Asistencias Pre-Operativas
-- Registro de charlas / reuniones pre-operativas: el coordinador elige los
-- tallys participantes, cada uno firma en pantalla, y se pueden adjuntar
-- evidencias (foto/video/PDF < 4 MB) que se suben a Google Drive.
-- Exporta un PDF con el formato de Registro de Asistencia (COSCO).
--
-- Los datos de la charla (tema, capacitador, actividad, hora) son
-- COMPARTIDOS para toda la reunión y se aplican a todos los participantes.
--
-- Ejecutar con: mysql -uroot portally_system < sql/019_asistencias_preoperativas.sql
-- Idempotente (CREATE TABLE IF NOT EXISTS).
-- ════════════════════════════════════════════════════════════════════

USE portally_system;

-- ── La reunión / charla ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS asistencias_preoperativas (
  id             INT(11)      NOT NULL AUTO_INCREMENT,
  tema           VARCHAR(150) NOT NULL,               -- actividad, ej. "Charla Pre Operativa"
  tipo_reunion   VARCHAR(40)  NOT NULL,               -- catálogo COSCO (charla_seguridad, capacitacion, …)
  lugar          VARCHAR(120) NULL,
  capacitador    VARCHAR(120) NOT NULL,
  turno          ENUM('dia','noche') NOT NULL,
  fecha          DATE         NOT NULL,
  hora           TIME         NULL,                   -- columna "HORAS" de la hoja (06:25)
  zona_trabajo   VARCHAR(80)  NULL,
  observaciones  TEXT         NULL,
  coordinador    VARCHAR(100) NOT NULL,               -- nombre de la sesión que registra
  coordinador_id INT(11)      NULL,                   -- user_id de la sesión
  created_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_aso_fecha (fecha),
  KEY ix_aso_turno (turno)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Participantes (un tally por fila), con su firma digital ───────────
CREATE TABLE IF NOT EXISTS asistencias_participantes (
  id                 INT(11)      NOT NULL AUTO_INCREMENT,
  asistencia_id      INT(11)      NOT NULL,
  colaborador_id     INT(11)      NULL,               -- FK; SET NULL si se borra el colaborador
  colaborador_nombre VARCHAR(150) NOT NULL,           -- copia congelada al registrar
  colaborador_dni    VARCHAR(8)   NULL,               -- copia congelada
  colaborador_cargo  VARCHAR(60)  NULL,               -- = funcion_principal al registrar
  firma_data         MEDIUMTEXT   NULL,               -- PNG de la firma en base64 (data URL)
  created_at         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_asp_asistencia (asistencia_id),
  KEY ix_asp_colaborador (colaborador_id),
  CONSTRAINT fk_asp_asistencia FOREIGN KEY (asistencia_id)
     REFERENCES asistencias_preoperativas(id) ON DELETE CASCADE,
  CONSTRAINT fk_asp_colaborador FOREIGN KEY (colaborador_id)
     REFERENCES colaboradores(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Evidencias (foto/video/PDF), subidas a Google Drive ──────────────
CREATE TABLE IF NOT EXISTS asistencias_evidencias (
  id             INT(11)      NOT NULL AUTO_INCREMENT,
  asistencia_id  INT(11)      NOT NULL,
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
  KEY ix_ase_asistencia (asistencia_id),
  CONSTRAINT fk_ase_asistencia FOREIGN KEY (asistencia_id)
     REFERENCES asistencias_preoperativas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
