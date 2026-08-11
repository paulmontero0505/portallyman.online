-- ════════════════════════════════════════════════════════════════════
-- ESTIBA_TURNO · 021 · Módulo de Reconocimiento Tally
-- Registro de reconocimientos / aspectos destacados del personal.
-- Espejo positivo del módulo de Incidencias.
-- Catálogos (competencia→concepto, nivel de impacto, zonas) viven FIJOS en
-- código: includes/reconocimientos_catalogo.php
-- Evidencia (foto) se guarda en uploads/reconocimientos/ y aquí sólo se
-- persiste la ruta relativa + la URL de Drive.
-- Ejecutar con: mysql -uroot portally_system < sql/021_reconocimientos.sql
-- Idempotente (CREATE TABLE IF NOT EXISTS).
-- ════════════════════════════════════════════════════════════════════

USE portally_system;

CREATE TABLE IF NOT EXISTS reconocimientos (
  id                  INT(11)      NOT NULL AUTO_INCREMENT,
  colaborador_id      INT(11)      NULL,                  -- FK; SET NULL si se borra el colaborador
  colaborador_nombre  VARCHAR(150) NOT NULL,              -- copia congelada al registrar
  colaborador_cargo   VARCHAR(60)  NULL,                  -- = funcion_principal al registrar
  competencia         VARCHAR(120) NOT NULL,              -- p.ej. "Autonomía"
  concepto            VARCHAR(255) NOT NULL,              -- derivado de la competencia (validado en servidor)
  impacto             ENUM('bueno','excelente','sobresaliente') NOT NULL,
  coordinador         VARCHAR(100) NOT NULL,              -- nombre de la sesión que registra
  coordinador_id      INT(11)      NULL,                  -- user_id de la sesión
  turno               ENUM('dia','noche') NOT NULL,
  fecha               DATE         NOT NULL,
  zona_trabajo        VARCHAR(80)  NOT NULL,
  detalle             TEXT         NULL,
  foto_path           VARCHAR(255) NULL,                  -- ruta relativa en uploads/reconocimientos
  foto_drive_url      VARCHAR(512) NULL,
  created_at          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_rec_fecha (fecha),
  KEY ix_rec_impacto (impacto),
  KEY ix_rec_colaborador (colaborador_id),
  CONSTRAINT fk_rec_colaborador FOREIGN KEY (colaborador_id)
     REFERENCES colaboradores(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
