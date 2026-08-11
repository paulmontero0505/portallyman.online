-- ════════════════════════════════════════════════════════════════════
-- ESTIBA_TURNO · 008 · Módulo de Incidencias
-- Registro de incidencias / puntos a mejorar del personal.
-- Catálogos (punto→competencia, impacto, zonas) viven FIJOS en código:
--   includes/incidencias_catalogo.php
-- Archivos (foto, hoja de declaración) se guardan en uploads/incidencias/
-- y aquí sólo se persiste la ruta relativa.
-- Ejecutar con: mysql -uroot estiba_turno < sql/008_incidencias.sql
-- Idempotente (CREATE TABLE IF NOT EXISTS).
-- ════════════════════════════════════════════════════════════════════

USE estiba_turno;

CREATE TABLE IF NOT EXISTS incidencias (
  id                  INT(11)      NOT NULL AUTO_INCREMENT,
  colaborador_id      INT(11)      NULL,                  -- FK; SET NULL si se borra el colaborador
  colaborador_nombre  VARCHAR(150) NOT NULL,              -- copia congelada al registrar
  colaborador_cargo   VARCHAR(60)  NULL,                  -- = funcion_principal al registrar
  punto_mejorar       VARCHAR(80)  NOT NULL,              -- p.ej. "Errores de pedeteo"
  competencia         VARCHAR(80)  NOT NULL,              -- derivada del punto (validada en servidor)
  impacto             ENUM('minimo','bajo','moderado','alto','critico') NOT NULL,
  coordinador         VARCHAR(100) NOT NULL,              -- nombre de la sesión que registra
  coordinador_id      INT(11)      NULL,                  -- user_id de la sesión
  turno               ENUM('dia','noche') NOT NULL,
  fecha               DATE         NOT NULL,
  zona_trabajo        VARCHAR(80)  NOT NULL,
  detalle             TEXT         NULL,
  foto_path           VARCHAR(255) NULL,                  -- ruta relativa en uploads/incidencias
  declaracion_path    VARCHAR(255) NULL,
  created_at          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_inc_fecha (fecha),
  KEY ix_inc_impacto (impacto),
  KEY ix_inc_colaborador (colaborador_id),
  CONSTRAINT fk_inc_colaborador FOREIGN KEY (colaborador_id)
     REFERENCES colaboradores(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
